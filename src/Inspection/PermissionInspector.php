<?php

declare(strict_types=1);

namespace Modufolio\Panel\Inspection;

use Modufolio\Panel\Blueprint\FieldAccess;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Resource\PanelResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The four permission layers, combined and read back without a request.
 *
 * Route roles, the operation hooks, the row scope and per-field access are
 * each plain code on a resource, which is what keeps them testable — and also
 * what keeps their combined effect invisible until someone signs in as each
 * role and clicks around. This asks every layer the question a request would,
 * for a stand-in user per role, and reports the answers side by side.
 *
 * The route collection is the single source for what is generated and which
 * roles a route names: feeding the configurator's config in as well would let
 * the two disagree. Resources are obtained through a callable the host owns,
 * so a resource with constructor dependencies comes from the container the
 * way it does at request time.
 *
 * Stand-in users carry the *literal* role, which is what a stored user's
 * `getRoles()` returns; the security layer's role hierarchy enters through
 * `$reachableRoles`. That split is deliberate: a hook that checks a literal
 * role while the route layer honours the hierarchy is exactly the divergence
 * this exists to show.
 *
 * @phpstan-import-type RoleVerdict from PermissionReport
 * @phpstan-import-type ResourceEntry from PermissionReport
 * @phpstan-import-type Note from PermissionReport
 */
final class PermissionInspector
{
    /**
     * Route-name suffixes per operation, for adapters that want the coarse
     * view a resource declares (`only()` / `except()`) rather than every route.
     */
    public const OPERATIONS = [
        'index'  => ['', '_export'],
        'create' => ['_create', '_store'],
        'edit'   => ['_edit', '_update', '_relation_options', '_relation_create', '_relation_store'],
        'delete' => ['_delete_preview', '_bulk_destroy', '_destroy'],
        'show'   => ['_show'],
    ];

    private const HOOKS = ['canView', 'canCreate', 'canEdit', 'canDelete', 'scopeQuery', 'readonlyFields'];

    /** @var \Closure(string): list<string> */
    private readonly \Closure $reachableRoles;

    /**
     * @param \Closure(class-string<PanelResource>): PanelResource $resources      how the host builds a resource
     * @param \Closure(string): list<string>|null                  $reachableRoles the security layer's hierarchy;
     *                                                                             a role reaches itself by default
     */
    public function __construct(
        private readonly RouteCollection $routes,
        private readonly \Closure $resources,
        private readonly FormResolver $forms,
        ?\Closure $reachableRoles = null,
    ) {
        $this->reachableRoles = $reachableRoles ?? static fn (string $role): array => [$role];
    }

    /**
     * Every resource class the generated routes name, once each, in route order.
     *
     * @return list<class-string<PanelResource>>
     */
    public static function resourceClassesIn(RouteCollection $routes): array
    {
        $classes = [];

        foreach ($routes as $route) {
            $class = $route->getDefault('resourceClass');

            if (is_string($class) && is_a($class, PanelResource::class, true) && !in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @param list<class-string<PanelResource>> $resourceClasses
     * @param list<string>                      $roles
     * @param \Closure(string): object          $userFactory a user carrying only the literal role
     */
    public function inspect(array $resourceClasses, array $roles, \Closure $userFactory): PermissionReport
    {
        $resources = [];
        $notes     = [];

        foreach ($resourceClasses as $class) {
            $resource = ($this->resources)($class);
            $entry    = $this->inspectResource($resource, $roles, $userFactory, $notes);

            $resources[$entry['key']] = $entry;
        }

        return new PermissionReport($roles, $resources, $notes);
    }

    /**
     * @param list<string> $roles
     * @param \Closure(string): object $userFactory
     * @param list<Note> $notes
     * @return ResourceEntry
     */
    private function inspectResource(PanelResource $resource, array $roles, \Closure $userFactory, array &$notes): array
    {
        $key       = $resource->key();
        $routes    = $this->routesOf($resource);
        $overrides = $this->overridesOf($resource);
        $declared  = $this->forms->fieldsFor($resource);
        $access    = $this->forms->accessFor($resource);
        $verdicts  = [];

        if ($routes !== [] && !$this->anyRouteGuarded($routes)) {
            $notes[] = $this->note('unguarded', $key, null, sprintf(
                'No generated route of "%s" names a role; every signed-in user reaches them.',
                $key,
            ));
        }

        foreach ($roles as $role) {
            $user      = $userFactory($role);
            $reachable = ($this->reachableRoles)($role);

            $admitted = [];
            foreach ($routes as $name => $route) {
                $admitted[$name] = $this->admits($route, $reachable);
            }

            $can = [
                'view'   => $resource->canView(null, $user),
                'create' => $resource->canCreate($user),
                'edit'   => $resource->canEdit(null, $user),
                'delete' => $resource->canDelete(null, $user),
            ];

            $fields = $this->fieldVerdicts($resource, $declared, $access, $user, $key, $role, $notes);

            $verdicts[$role] = ['routes' => $admitted, 'can' => $can, 'fields' => $fields];

            $this->noteRouteAdmitsHookDenies($key, $role, $admitted, $can, $notes);
        }

        foreach (['canView', 'canCreate', 'canEdit', 'canDelete'] as $hook) {
            if ($overrides[$hook]) {
                $notes[] = $this->note('record_dependent', $key, null, sprintf(
                    '%s overrides %s(); the verdicts shown are for the type, a record may answer differently.',
                    $key,
                    $hook,
                ));
            }
        }

        $this->noteHierarchyDivergence($key, $roles, $verdicts, $notes);

        return [
            'key'       => $key,
            'class'     => $resource::class,
            'prefix'    => $this->prefixOf($routes, $key),
            'routes'    => array_keys($routes),
            'overrides' => $overrides,
            'roles'     => $verdicts,
        ];
    }

    // ── Routes ───────────────────────────────────────────────────────────────

    /**
     * The routes that belong to a resource: those the loader stamped with its
     * class, plus any named by its key convention (`{key}`, `{key}_*`) — which
     * is how a hand-written controller's routes are named too.
     *
     * @return array<string, Route>
     */
    private function routesOf(PanelResource $resource): array
    {
        $key   = $resource->key();
        $found = [];

        foreach ($this->routes as $name => $route) {
            $name = (string) $name;

            $byClass = $route->getDefault('resourceClass') === $resource::class;
            $byName  = $name === $key || str_starts_with($name, $key . '_');

            if ($byClass || $byName) {
                $found[$name] = $route;
            }
        }

        return $found;
    }

    /**
     * Whether a route admits a role, by the loader's `_is_granted_roles`
     * default: a list of AND-ed groups, each an OR-ed role list. No default
     * means nobody is refused at the route.
     *
     * @param list<string> $reachable
     */
    private function admits(Route $route, array $reachable): bool
    {
        $groups = $route->getDefault('_is_granted_roles');

        if (!is_array($groups)) {
            return true;
        }

        foreach ($groups as $group) {
            $required = is_array($group) ? array_values(array_filter($group, 'is_string')) : [];

            if ($required !== [] && array_intersect($required, $reachable) === []) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, Route> $routes */
    private function anyRouteGuarded(array $routes): bool
    {
        foreach ($routes as $route) {
            if (is_array($route->getDefault('_is_granted_roles'))) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, Route> $routes */
    private function prefixOf(array $routes, string $key): ?string
    {
        $index = $routes[$key] ?? null;

        if ($index === null) {
            return null;
        }

        $path = $index->getPath();

        return str_ends_with($path, '/' . $key) ? substr($path, 0, -strlen('/' . $key)) : $path;
    }

    // ── Hooks ────────────────────────────────────────────────────────────────

    /**
     * Which hooks the resource overrides. An overridden hook may answer
     * per record; the verdict shown for the type is then only half the story.
     *
     * @return array{canView: bool, canCreate: bool, canEdit: bool, canDelete: bool, scopeQuery: bool, readonlyFields: bool}
     */
    private function overridesOf(PanelResource $resource): array
    {
        $overrides = [];

        foreach (self::HOOKS as $hook) {
            $overrides[$hook] = (new \ReflectionMethod($resource, $hook))->getDeclaringClass()->getName() !== PanelResource::class;
        }

        return [
            'canView'        => $overrides['canView'],
            'canCreate'      => $overrides['canCreate'],
            'canEdit'        => $overrides['canEdit'],
            'canDelete'      => $overrides['canDelete'],
            'scopeQuery'     => $overrides['scopeQuery'],
            'readonlyFields' => $overrides['readonlyFields'],
        ];
    }

    // ── Fields ───────────────────────────────────────────────────────────────

    /**
     * What this user sees of the form: fields removed by a read denial,
     * marked read-only by a write denial, or frozen by the resource for the
     * type. Metadata only — the relation options a rendered form would
     * resolve are not consulted, so nothing here reads rows.
     *
     * @param list<array<string, mixed>>                              $declared
     * @param array<string, array{read?: callable, write?: callable}> $access
     * @param list<Note>                                              $notes
     * @return array{readable: list<string>, readDenied: list<string>, writeDenied: list<string>, frozen: list<string>}
     */
    private function fieldVerdicts(
        PanelResource $resource,
        array $declared,
        array $access,
        object $user,
        string $key,
        string $role,
        array &$notes,
    ): array {
        $declaredKeys     = self::keysOf($declared);
        $declaredReadonly = self::readonlyKeysOf($declared);

        try {
            $resolved = FieldAccess::resolve($declared, $access, $user, null);
        } catch (\Throwable $e) {
            $notes[] = $this->note('access_threw', $key, $role, sprintf(
                'An access closure of "%s" needs a record and threw for the type (%s); field verdicts are unknown for %s.',
                $key,
                $e->getMessage(),
                $role,
            ));

            return ['readable' => [], 'readDenied' => [], 'writeDenied' => [], 'frozen' => []];
        }

        $readable    = self::keysOf($resolved);
        $writeDenied = array_values(array_diff(self::readonlyKeysOf($resolved), $declaredReadonly));

        return [
            'readable'    => $readable,
            'readDenied'  => array_values(array_diff($declaredKeys, $readable)),
            'writeDenied' => $writeDenied,
            'frozen'      => array_values(array_filter($resource->readonlyFields(null, $user), 'is_string')),
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<string>
     */
    private static function keysOf(array $fields): array
    {
        $keys = [];

        foreach ($fields as $field) {
            $keys[] = (string) ($field['key'] ?? '');
        }

        return $keys;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<string>
     */
    private static function readonlyKeysOf(array $fields): array
    {
        $keys = [];

        foreach ($fields as $field) {
            $props = is_array($field['props'] ?? null) ? $field['props'] : [];

            if (($props['readonly'] ?? false) === true) {
                $keys[] = (string) ($field['key'] ?? '');
            }
        }

        return $keys;
    }

    // ── Notes ────────────────────────────────────────────────────────────────

    /**
     * A route that lets a role in while the matching hook refuses it: the
     * request reaches the controller only to be turned away.
     *
     * @param array<string, bool>                                    $admitted
     * @param array{view: bool, create: bool, edit: bool, delete: bool} $can
     * @param list<Note>                                             $notes
     */
    private function noteRouteAdmitsHookDenies(string $key, string $role, array $admitted, array $can, array &$notes): void
    {
        $hookFor = ['index' => 'view', 'show' => 'view', 'create' => 'create', 'edit' => 'edit', 'delete' => 'delete'];

        foreach (self::OPERATIONS as $operation => $suffixes) {
            $hook = $hookFor[$operation];

            foreach ($suffixes as $suffix) {
                if (($admitted[$key . $suffix] ?? false) && !$can[$hook]) {
                    $notes[] = $this->note('route_admits_hook_denies', $key, $role, sprintf(
                        'Route "%s" admits %s, but can%s() refuses; the request reaches the controller only to be denied.',
                        $key . $suffix,
                        $role,
                        ucfirst($hook),
                    ));

                    continue 2;
                }
            }
        }
    }

    /**
     * A role that reaches another through the hierarchy yet gets less than
     * it: the sign of a hook or an access closure checking a literal role.
     *
     * @param list<string>              $roles
     * @param array<string, RoleVerdict> $verdicts
     * @param list<Note>                $notes
     */
    private function noteHierarchyDivergence(string $key, array $roles, array $verdicts, array &$notes): void
    {
        foreach ($roles as $role) {
            foreach (($this->reachableRoles)($role) as $reached) {
                if ($reached === $role || !isset($verdicts[$reached], $verdicts[$role])) {
                    continue;
                }

                $lost = [];

                foreach ($verdicts[$reached]['can'] as $hook => $allowed) {
                    if ($allowed && !$verdicts[$role]['can'][$hook]) {
                        $lost[] = 'can' . ucfirst($hook) . '()';
                    }
                }

                foreach ($verdicts[$reached]['routes'] as $name => $allowed) {
                    if ($allowed && !($verdicts[$role]['routes'][$name] ?? false)) {
                        $lost[] = 'route ' . $name;
                    }
                }

                foreach (array_diff($verdicts[$reached]['fields']['readable'], $verdicts[$role]['fields']['readable']) as $field) {
                    $lost[] = 'reading ' . $field;
                }

                foreach (array_diff($verdicts[$role]['fields']['writeDenied'], $verdicts[$reached]['fields']['writeDenied']) as $field) {
                    $lost[] = 'writing ' . $field;
                }

                if ($lost !== []) {
                    $notes[] = $this->note('hierarchy_divergence', $key, $role, sprintf(
                        '%s reaches %s through the hierarchy but gets less on "%s": %s. A check is reading the literal role.',
                        $role,
                        $reached,
                        $key,
                        implode(', ', $lost),
                    ));
                }
            }
        }
    }

    /** @return Note */
    private function note(string $kind, string $resource, ?string $role, string $message): array
    {
        return ['kind' => $kind, 'resource' => $resource, 'role' => $role, 'message' => $message];
    }
}
