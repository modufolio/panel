<?php

declare(strict_types=1);

namespace Modufolio\Panel\Routing;

use Modufolio\Panel\Http\ResourceController;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\PanelResourceConfigurator;
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
 * Index, show and export always come from the declaration alone; the write
 * routes, relation endpoints and delete preview appear once the resource
 * declares a form, and the board move once it declares a board.
 *
 * The loader never constructs a resource itself. It asks the `$resources`
 * resolver the host hands it — which in practice is the host's container —
 * so a resource is built exactly one way, with whatever constructor
 * dependencies it declares. Route collections are loaded lazily, on the
 * router's first use, by which point the container exists.
 *
 * The generated names and paths match what the hand-written controllers
 * already use (`movies`, `movies_show`, `/panel/movies/{uuid}`), so a resource
 * can graduate to a real controller later without any URL changing.
 */
final class PanelResourceRouteLoader extends Loader
{
    /**
     * @param \Closure(class-string<PanelResource>): PanelResource $resources
     *        how a configured class becomes an instance — the host's
     *        container, in practice
     * @param class-string $controllerClass what every generated route dispatches
     *        to, as `[$controllerClass, 'handle']`. The package ships one; a
     *        host names its own only when it has outgrown it.
     */
    public function __construct(
        private readonly FileLocatorInterface $fileLocator,
        private readonly \Closure $resources,
        private readonly string $controllerClass = ResourceController::class,
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
            $instance = $this->resourceFor($resourceClass);
            $key      = $instance->key();
            $prefix   = $options->prefixOr($this->prefix);
            // The resource's own Permissions name the roles; the kernel
            // enforces them on every route generated below. No database is
            // touched: roles() is a declaration, read at route-build time.
            $roles    = $instance->permissions()->roles();

            // The write trio needs a form to render and validate against; a
            // resource without one stays read-only whatever the options say.
            // form() is a static declaration here — nothing is guessed until
            // a request needs the form — so the loader stays database-free.
            $hasForm = $instance->form() !== null;

            if ($options->generates('index')) {
                $index = $this->createRoute("{$prefix}/{$key}", ['GET'], 'index', $resourceClass, $roles);

                // The menu entry rides the route it links to, so a host's
                // navigation finds every resource by walking its routes —
                // see ResourceMenu — and the route's roles gate the entry.
                if (($menu = $instance->menu()) !== null) {
                    $index->setDefault(ResourceMenu::DEFAULT, $menu->toArray());
                }

                $routes->add($key, $index);

                // Downloading the list is reading the list, so this rides the
                // index's opt-in and its roles rather than having its own. It
                // is a POST because the format, the column list and any
                // selection travel in the body; the *filters* travel in the
                // query string, exactly as they do for the page itself.
                $routes->add(
                    "{$key}_export",
                    $this->createRoute("{$prefix}/{$key}/export", ['POST'], 'export', $resourceClass, $roles),
                );
            }

            if ($hasForm && $options->generates('create')) {
                $routes->add(
                    "{$key}_create",
                    $this->createRoute("{$prefix}/{$key}/create", ['GET'], 'create', $resourceClass, $roles),
                );
                $routes->add(
                    "{$key}_store",
                    $this->createRoute("{$prefix}/{$key}", ['POST'], 'store', $resourceClass, $roles),
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
                        $roles,
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
                        $roles,
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
                        $roles,
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
                        $roles,
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
                        $roles,
                        ['uuid' => Uuid::PATTERN, 'field' => '[a-zA-Z0-9_]+'],
                    ),
                );
            }

            // Moving a card on a board: which column it landed in and which
            // cards it landed between. POST rather than PUT because the
            // neighbours travel in the body and the server decides the
            // resulting position — the client never sends one.
            //
            // Conditioned on a board being declared rather than on a form
            // existing: a board is a way of reading records, and grouping them
            // by a field they already have does not require a form to edit
            // them through. The move itself is still an edit, and the handler
            // asks Permissions::edit() before writing anything.
            foreach ($instance->views() as $declaredView) {
                if (!$declaredView->isBoard()) {
                    continue;
                }

                $routes->add(
                    "{$key}_board_move",
                    $this->createRoute(
                        "{$prefix}/{$key}/{uuid}/board-move",
                        ['POST'],
                        'boardMove',
                        $resourceClass,
                        $roles,
                        ['uuid' => Uuid::PATTERN],
                    ),
                );

                break;
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
                        $roles,
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
                        $roles,
                    ),
                );

                $routes->add(
                    "{$key}_destroy",
                    $this->createRoute(
                        "{$prefix}/{$key}/{uuid}",
                        ['DELETE'],
                        'destroy',
                        $resourceClass,
                        $roles,
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
                    $roles,
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
     * The configured class, as the host builds it.
     *
     * @param string $resourceClass A name from the config file, verified here
     *                              rather than trusted.
     */
    private function resourceFor(string $resourceClass): PanelResource
    {
        if (!class_exists($resourceClass)) {
            throw new \InvalidArgumentException(sprintf(
                'Configured panel resource "%s" does not exist.',
                $resourceClass,
            ));
        }

        if (!is_a($resourceClass, PanelResource::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Configured panel resource "%s" is not a %s.',
                $resourceClass,
                PanelResource::class,
            ));
        }

        $resource = ($this->resources)($resourceClass);

        if (!$resource instanceof $resourceClass) {
            throw new \LogicException(sprintf(
                'The resource resolver returned %s for "%s".',
                get_debug_type($resource),
                $resourceClass,
            ));
        }

        return $resource;
    }

    /**
     * @param list<string>          $methods
     * @param list<string>          $roles        what the resource's Permissions require; empty gates nothing
     * @param array<string, string> $requirements
     */
    private function createRoute(
        string $path,
        array $methods,
        string $operation,
        string $resourceClass,
        array $roles,
        array $requirements = [],
    ): Route {
        $defaults = [
            '_controller'   => [$this->controllerClass, 'handle'],
            'resourceClass' => $resourceClass,
            'operation'     => $operation,
        ];

        // Same route default #[IsGranted] writes, so the kernel enforces a
        // generated resource exactly as it does a hand-written one — with the
        // role hierarchy, and before the controller is reached. One group:
        // any declared role admits.
        if ($roles !== []) {
            $defaults['_is_granted_roles'] = [$roles];
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
