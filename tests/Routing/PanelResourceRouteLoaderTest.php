<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\PanelResourceConfigurator;
use Modufolio\Panel\Routing\PanelResourceRouteLoader;
use Modufolio\Panel\Routing\ResourceMenu;
use Modufolio\Panel\Routing\Uuid;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The loader end to end: a config file, a configurator, real resources, and
 * the RouteCollection that comes out.
 *
 * This is the package's only integration test, and it earns that because the
 * loader is where three things meet — what the configurator registered, what
 * each resource declares about itself, and what the host named as its
 * controller. Unit-testing any one of them in isolation would not have caught
 * a resource whose write routes appear because the *options* allow them while
 * the resource has no form to render.
 */
final class PanelResourceRouteLoaderTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
    }

    /** The named route, failing the test rather than dereferencing null. */
    private function route(RouteCollection $routes, string $name): Route
    {
        return $routes->get($name) ?? self::fail(sprintf('Route "%s" was not generated.', $name));
    }

    /**
     * Writes a real config file, because that is what the loader consumes —
     * `include`-ing a closure that configures a PanelResourceConfigurator.
     */
    private function load(string $body, string $prefix = '/panel'): RouteCollection
    {
        return $this->loadWith($body, self::resolver(), $prefix);
    }

    private function loadWith(string $body, \Closure $resolver, string $prefix = '/panel'): RouteCollection
    {
        $file = tempnam(sys_get_temp_dir(), 'panel_resources_') . '.php';
        file_put_contents($file, "<?php\n\nuse " . PanelResourceConfigurator::class . ";\n\nreturn {$body};\n");
        $this->tempFiles[] = $file;

        $locator = new class ($file) implements FileLocatorInterface {
            public function __construct(private readonly string $file) {}

            public function locate(string $name, ?string $currentPath = null, bool $first = true): string
            {
                return $this->file;
            }
        };

        return (new PanelResourceRouteLoader($locator, $resolver, FixtureController::class, $prefix))
            ->load($file, 'panel_resource');
    }

    private function readOnlyConfig(): string
    {
        return 'function (PanelResourceConfigurator $panel): void {
            $panel->resource(\\' . ReadOnlyResource::class . '::class);
        }';
    }

    // ── What gets generated ──────────────────────────────────────────────────

    public function testAResourceWithNoFormFieldsGetsOnlyReadRoutes(): void
    {
        $routes = $this->load($this->readOnlyConfig());

        $names = array_keys($routes->all());
        sort($names);

        self::assertSame(['events', 'events_export', 'events_show'], $names);
    }

    /**
     * Declaring form fields is the switch. A read-only resource is one that
     * simply has no form — not one that overrides three permission hooks.
     */
    public function testDeclaringFormFieldsTurnsOnTheWriteRoutes(): void
    {
        $routes = $this->load('function (PanelResourceConfigurator $panel): void {
            $panel->resource(\\' . WritableResource::class . '::class);
        }');

        $names = array_keys($routes->all());

        foreach (['actors_create', 'actors_store', 'actors_edit', 'actors_update', 'actors_destroy'] as $expected) {
            self::assertContains($expected, $names);
        }
    }

    public function testTheIndexRouteIsTheBarePrefixedKey(): void
    {
        $route = $this->load($this->readOnlyConfig())->get('events');

        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/panel/events', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
    }

    /** The host names its controller; the loader never mentions one. */
    public function testEveryRouteDispatchesToTheControllerTheHostNamed(): void
    {
        $routes = $this->load($this->readOnlyConfig());

        foreach ($routes->all() as $name => $route) {
            self::assertSame(
                [FixtureController::class, 'handle'],
                $route->getDefault('_controller'),
                $name . ' must dispatch to the configured controller',
            );
        }
    }

    // ── The uuid requirement ─────────────────────────────────────────────────

    /**
     * Without this, `/panel/events/create` matches the show route and a
     * resource loses its create page to its own detail view.
     */
    public function testShowRequiresAUuidSoItCannotSwallowNamedRoutes(): void
    {
        $show = $this->route($this->load($this->readOnlyConfig()), 'events_show');

        self::assertSame('/panel/events/{uuid}', $show->getPath());
        self::assertSame(Uuid::PATTERN, $show->getRequirement('uuid'));
        self::assertSame(0, preg_match('/^' . $show->getRequirement('uuid') . '$/D', 'create'));
    }

    /**
     * Not just `show`: every route taking a `{uuid}` must constrain it. Seven
     * do, and a mutation dropping the requirement from any one of them passed
     * a test that only inspected the show route.
     */
    public function testEveryRouteTakingAUuidConstrainsIt(): void
    {
        $routes = $this->load('function (PanelResourceConfigurator $panel): void {
            $panel->resource(\\' . WritableResource::class . '::class);
        }');

        $checked = 0;

        foreach ($routes->all() as $name => $route) {
            if (!str_contains($route->getPath(), '{uuid}')) {
                continue;
            }

            self::assertSame(
                Uuid::PATTERN,
                $route->getRequirement('uuid'),
                $name . ' takes a {uuid} but does not constrain it',
            );
            $checked++;
        }

        self::assertGreaterThan(1, $checked, 'Expected several uuid-addressed routes.');
    }

    /**
     * The full route table for a writable resource: name, path and methods.
     *
     * Deliberately exhaustive rather than spot-checked. Each of these paths is
     * half a contract with the controller — `_update` taking `{uuid}` is what
     * lets it look a record up the same way `show` does — and a mutation
     * changing one slipped past a suite that only asserted `index` and `show`.
     */
    public function testTheGeneratedRouteTableIsExact(): void
    {
        $routes = $this->load('function (PanelResourceConfigurator $panel): void {
            $panel->resource(\\' . WritableResource::class . '::class);
        }');

        $actual = [];
        foreach ($routes->all() as $name => $route) {
            $actual[$name] = [$route->getPath(), $route->getMethods()];
        }
        ksort($actual);

        self::assertSame([
            'actors'                  => ['/panel/actors', ['GET']],
            'actors_bulk_destroy'     => ['/panel/actors/bulk-delete', ['POST']],
            'actors_create'           => ['/panel/actors/create', ['GET']],
            'actors_delete_preview'   => ['/panel/actors/{uuid}/delete-preview', ['GET']],
            'actors_destroy'          => ['/panel/actors/{uuid}', ['DELETE']],
            'actors_edit'             => ['/panel/actors/{uuid}/edit', ['GET']],
            'actors_export'           => ['/panel/actors/export', ['POST']],
            'actors_relation_create'  => ['/panel/actors/relations/{field}', ['POST']],
            'actors_relation_options' => ['/panel/actors/relations/{field}', ['GET']],
            'actors_relation_store'   => ['/panel/actors/{uuid}/relations/{field}', ['POST']],
            'actors_show'             => ['/panel/actors/{uuid}', ['GET']],
            'actors_store'            => ['/panel/actors', ['POST']],
            'actors_update'           => ['/panel/actors/{uuid}', ['PUT']],
        ], $actual);
    }

    // ── Options ──────────────────────────────────────────────────────────────

    public function testOnlyNarrowsWhatIsGenerated(): void
    {
        $routes = $this->load('function (PanelResourceConfigurator $panel): void {
            $panel->resource(\\' . WritableResource::class . '::class)->only([\'index\', \'show\']);
        }');

        $names = array_keys($routes->all());

        self::assertContains('actors', $names);
        self::assertContains('actors_show', $names);
        self::assertNotContains('actors_create', $names);
        self::assertNotContains('actors_destroy', $names);
    }

    public function testExceptRemovesOnlyWhatItNames(): void
    {
        $routes = $this->load('function (PanelResourceConfigurator $panel): void {
            $panel->resource(\\' . WritableResource::class . '::class)->except([\'delete\']);
        }');

        $names = array_keys($routes->all());

        self::assertContains('actors_create', $names);
        self::assertNotContains('actors_destroy', $names);
        self::assertNotContains('actors_bulk_destroy', $names);
    }

    /** The roles the resource's Permissions name land on every route, so the kernel enforces them before dispatch. */
    public function testRolesAreAttachedToEveryGeneratedRoute(): void
    {
        $routes = $this->load('function (PanelResourceConfigurator $panel): void {
            $panel->resource(\\' . GuardedReadOnlyResource::class . '::class);
        }');

        foreach ($routes->all() as $name => $route) {
            self::assertSame(
                [['ROLE_ADMIN']],
                $route->getDefault('_is_granted_roles'),
                $name . ' must carry the declared roles',
            );
        }
    }

    /** The resource's own menu() rides the index route alone: that is what it links to, and the export shares its roles anyway. */
    public function testTheMenuEntryRidesTheIndexRoute(): void
    {
        $routes = $this->load('function (PanelResourceConfigurator $panel): void {
            $panel->resource(\\' . GuardedReadOnlyResource::class . '::class);
        }');

        self::assertSame(
            ['label' => 'Events', 'icon' => 'calendar', 'group' => 'Main', 'order' => 16],
            $this->route($routes, 'events')->getDefault(ResourceMenu::DEFAULT),
        );

        foreach ($routes->all() as $name => $route) {
            if ($name !== 'events') {
                self::assertNull($route->getDefault(ResourceMenu::DEFAULT), $name . ' carries no menu entry.');
            }
        }

        self::assertSame(
            [['route' => 'events', 'label' => 'Events', 'icon' => 'calendar', 'group' => 'Main', 'order' => 16, 'roles' => ['ROLE_ADMIN']]],
            ResourceMenu::fromRoutes($routes),
            'The host reads the entry back with the roles the route enforces.',
        );
    }

    public function testAResourceWithoutAMenuEntryIsNotInTheMenu(): void
    {
        $routes = $this->load($this->readOnlyConfig());

        self::assertNull($this->route($routes, 'events')->getDefault(ResourceMenu::DEFAULT));
        self::assertSame([], ResourceMenu::fromRoutes($routes));
    }

    public function testAResourceWithoutRolesCarriesNoRoleGate(): void
    {
        $route = $this->route($this->load($this->readOnlyConfig()), 'events');

        self::assertNull($route->getDefault('_is_granted_roles'));
    }

    public function testThePrefixCanBeOverriddenPerResource(): void
    {
        $routes = $this->load('function (PanelResourceConfigurator $panel): void {
            $panel->resource(\\' . ReadOnlyResource::class . '::class)->prefix(\'/admin\');
        }');

        self::assertSame('/admin/events', $this->route($routes, 'events')->getPath());
    }

    public function testTheDefaultPrefixComesFromTheLoader(): void
    {
        $routes = $this->load($this->readOnlyConfig(), prefix: '/backoffice');

        self::assertSame('/backoffice/events', $this->route($routes, 'events')->getPath());
    }

    // ── Refusals ─────────────────────────────────────────────────────────────

    public function testAnUnknownResourceClassIsRefusedLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $this->load('function (PanelResourceConfigurator $panel): void {
            $panel->resource(\'Fixture\\\\Nope\');
        }');
    }

    /**
     * The loader never calls `new` itself: a resource with constructor
     * dependencies is the resolver's business, so its routes generate like any
     * other's. This is what lets a resource take its collaborators through the
     * constructor rather than pulling them in afterwards.
     */
    public function testAResourceWithConstructorArgumentsIsBuiltByTheResolver(): void
    {
        $routes = $this->loadWith(
            'function (PanelResourceConfigurator $panel): void {
                $panel->resource(\\' . NeedsConstructorArgsResource::class . '::class);
            }',
            static function (string $class): PanelResource {
                /** @var class-string<PanelResource> $class */
                return $class === NeedsConstructorArgsResource::class
                    ? new NeedsConstructorArgsResource('reports')
                    : new $class();
            },
        );

        self::assertSame('/panel/reports', $this->route($routes, 'reports')->getPath());
    }

    /** A resolver that answers with something else is a wiring bug, and says so. */
    public function testAResolverReturningTheWrongClassIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/returned .* for/');

        $this->loadWith($this->readOnlyConfig(), static fn (string $class): PanelResource => new WritableResource());
    }

    /** Every fixture resource constructs bare, so the default resolver is `new`. */
    private static function resolver(): \Closure
    {
        return static function (string $class): PanelResource {
            /** @var class-string<PanelResource> $class */
            return new $class();
        };
    }

    public function testTheLoaderOnlySupportsItsOwnType(): void
    {
        $locator = new class implements FileLocatorInterface {
            public function locate(string $name, ?string $currentPath = null, bool $first = true): string
            {
                return $name;
            }
        };

        $loader = new PanelResourceRouteLoader($locator, self::resolver(), FixtureController::class);

        self::assertTrue($loader->supports('anything', 'panel_resource'));
        self::assertFalse($loader->supports('anything', 'yaml'));
        self::assertFalse($loader->supports('anything', null));
    }
}
