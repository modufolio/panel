<?php

declare(strict_types=1);

namespace Modufolio\Panel\Routing;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\PanelResourceConfigurator;
use Modufolio\Panel\Resource\PanelResourceOptions;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Generate a resource's panel routes from its PanelResource declaration.
 *
 * Same shape as JsonApiRouteLoader, aimed at the panel rather than the API: a
 * config file registers resource classes, and each one yields the routes its
 * listing needs, dispatched to a single generic controller. A resource that
 * only lists and shows records therefore needs no controller and no #[Route]
 * of its own — the route name, path and drawer wiring all derive from
 * `PanelResource::key()`.
 *
 * Deliberately narrow: index and show only. Create/edit/update are form work,
 * where the bespoke controller is still the honest answer — generating a CRUD
 * skeleton would freeze conventions that are still moving.
 *
 * The generated names and paths match what the hand-written controllers
 * already use (`movies`, `movies_show`, `/panel/movies/{uuid}`), so a resource
 * can graduate to a real controller later without any URL changing.
 */
final class PanelResourceRouteLoader extends Loader
{
    public function __construct(
        private readonly FileLocatorInterface $fileLocator,
        private readonly string $controllerClass,
        private readonly string $prefix = '/panel',
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $configFile = include $this->fileLocator->locate($resource);

        $configurator = new PanelResourceConfigurator();

        if (is_callable($configFile)) {
            $configFile($configurator);
        }

        $routes = new RouteCollection();

        foreach ($configurator->buildConfig() as $resourceClass => $options) {
            $instance = $this->instantiate($resourceClass);
            $key      = $instance->key();
            $prefix   = $options->prefixOr($this->prefix);

            // The write trio needs a form to render and validate against; a
            // resource without one stays read-only whatever the options say.
            // Either declaration style counts — the guessed one is still a
            // static key list here, so the loader stays database-free.
            $hasForm = $instance->formFields() !== null || $instance->formFieldKeys() !== null;

            if ($options->generates('index')) {
                $routes->add(
                    $key,
                    $this->createRoute("{$prefix}/{$key}", ['GET'], 'index', $resourceClass, $options),
                );

                // Downloading the list is reading the list, so this rides the
                // index's opt-in and its roles rather than having its own. It
                // is a POST because the format, the column list and any
                // selection travel in the body; the *filters* travel in the
                // query string, exactly as they do for the page itself.
                $routes->add(
                    "{$key}_export",
                    $this->createRoute("{$prefix}/{$key}/export", ['POST'], 'export', $resourceClass, $options),
                );
            }

            if ($hasForm && $options->generates('create')) {
                $routes->add(
                    "{$key}_create",
                    $this->createRoute("{$prefix}/{$key}/create", ['GET'], 'create', $resourceClass, $options),
                );
                $routes->add(
                    "{$key}_store",
                    $this->createRoute("{$prefix}/{$key}", ['POST'], 'store', $resourceClass, $options),
                );
            }

            if ($hasForm && $options->generates('edit')) {
                $routes->add(
                    "{$key}_edit",
                    $this->createRoute(
                        "{$prefix}/{$key}/{uuid}/edit",
                        ['GET'],
                        'edit',
                        $resourceClass,
                        $options,
                        ['uuid' => Uuid::PATTERN],
                    ),
                );
                $routes->add(
                    "{$key}_update",
                    $this->createRoute(
                        "{$prefix}/{$key}/{uuid}",
                        ['PUT'],
                        'update',
                        $resourceClass,
                        $options,
                        ['uuid' => Uuid::PATTERN],
                    ),
                );
            }

            // Relation options for the form's searchable selects. Emitted with
            // the form routes because it serves the form, and gated by the
            // same roles — a search endpoint that outlived its form's
            // permissions would be a way to read a table sideways.
            if ($hasForm && ($options->generates('create') || $options->generates('edit'))) {
                $routes->add(
                    "{$key}_relation_options",
                    $this->createRoute(
                        "{$prefix}/{$key}/relations/{field}",
                        ['GET'],
                        'relationOptions',
                        $resourceClass,
                        $options,
                        // Dots address a repeater's sub-field (`cast.actor_id`).
                        ['field' => '[a-zA-Z0-9_.]+'],
                    ),
                );

                // The picker's "Create …" row: POST on the same path, gated
                // by the same roles, and refused server-side unless the
                // target is creatable from its label alone — the client's
                // offer is a convenience, this check is the control.
                $routes->add(
                    "{$key}_relation_create",
                    $this->createRoute(
                        "{$prefix}/{$key}/relations/{field}",
                        ['POST'],
                        'relationCreate',
                        $resourceClass,
                        $options,
                        ['field' => '[a-zA-Z0-9_.]+'],
                    ),
                );

                // Adding one row to a record's relation from its drawer,
                // without going to the full form. Adding is editing, so it
                // rides the same opt-in and the same roles.
                $routes->add(
                    "{$key}_relation_store",
                    $this->createRoute(
                        "{$prefix}/{$key}/{uuid}/relations/{field}",
                        ['POST'],
                        'relationStore',
                        $resourceClass,
                        $options,
                        ['uuid' => Uuid::PATTERN, 'field' => '[a-zA-Z0-9_]+'],
                    ),
                );
            }

            if ($hasForm && $options->generates('delete')) {
                // What deleting this record would do, asked before anyone
                // commits to it — the confirmation's data source.
                $routes->add(
                    "{$key}_delete_preview",
                    $this->createRoute(
                        "{$prefix}/{$key}/{uuid}/delete-preview",
                        ['GET'],
                        'deletePreview',
                        $resourceClass,
                        $options,
                        ['uuid' => Uuid::PATTERN],
                    ),
                );

                // Deleting a selection. POST rather than DELETE because the
                // id list travels in the body, and no priority juggling is
                // needed against `/{key}/{uuid}`: that route requires a uuid,
                // which "bulk-delete" is not. Rides the delete opt-in and its
                // roles — it is the same permission, applied to many rows.
                $routes->add(
                    "{$key}_bulk_destroy",
                    $this->createRoute(
                        "{$prefix}/{$key}/bulk-delete",
                        ['POST'],
                        'bulkDestroy',
                        $resourceClass,
                        $options,
                    ),
                );

                $routes->add(
                    "{$key}_destroy",
                    $this->createRoute(
                        "{$prefix}/{$key}/{uuid}",
                        ['DELETE'],
                        'destroy',
                        $resourceClass,
                        $options,
                        ['uuid' => Uuid::PATTERN],
                    ),
                );
            }

            if ($options->generates('show')) {
                // priority -1 and the uuid requirement together keep this from
                // swallowing sibling routes such as `/{key}/create`, matching
                // what the hand-written controllers do.
                $route = $this->createRoute(
                    "{$prefix}/{$key}/{uuid}",
                    ['GET'],
                    'show',
                    $resourceClass,
                    $options,
                    ['uuid' => Uuid::PATTERN],
                );
                $route->setDefault('_priority', -1);

                $routes->add("{$key}_show", $route);
            }
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'panel_resource' === $type;
    }

    /**
     * A resource names its own route prefix and declares its own form;
     * instantiating it is the only way to ask, and a PanelResource is a
     * declaration object with no side effects.
     *
     * @param class-string<PanelResource> $resourceClass
     */
    private function instantiate(string $resourceClass): PanelResource
    {
        if (!class_exists($resourceClass)) {
            throw new \InvalidArgumentException(sprintf(
                'Configured panel resource "%s" does not exist.',
                $resourceClass,
            ));
        }

        $constructor = (new \ReflectionClass($resourceClass))->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new \InvalidArgumentException(sprintf(
                'Panel resource "%s" has required constructor arguments, so its routes cannot be generated. '
                . 'Give it a no-argument constructor or declare its routes by hand.',
                $resourceClass,
            ));
        }

        $resource = new $resourceClass();

        if (!$resource instanceof PanelResource) {
            throw new \InvalidArgumentException(sprintf(
                'Configured panel resource "%s" is not a %s.',
                $resourceClass,
                PanelResource::class,
            ));
        }

        return $resource;
    }

    /**
     * @param list<string>          $methods
     * @param array<string, string> $requirements
     */
    private function createRoute(
        string $path,
        array $methods,
        string $operation,
        string $resourceClass,
        PanelResourceOptions $options,
        array $requirements = [],
    ): Route {
        $defaults = [
            '_controller'   => [$this->controllerClass, 'handle'],
            'resourceClass' => $resourceClass,
            'operation'     => $operation,
        ];

        // Same route default #[IsGranted] writes, so the kernel enforces a
        // generated resource exactly as it does a hand-written one — with the
        // role hierarchy, and before the controller is reached.
        if (($roleGroups = $options->roleGroups()) !== []) {
            $defaults['_is_granted_roles'] = $roleGroups;
        }

        return new Route(
            path: $path,
            defaults: $defaults,
            requirements: $requirements,
            methods: $methods,
            options: ['expose' => true],
        );
    }
}
