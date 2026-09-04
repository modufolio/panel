<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\PanelResourceConfigurator;
use Modufolio\Panel\Routing\PanelResourceRouteLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RouteCollection;

/**
 * The generated routes must survive being dumped to a cached PHP file.
 *
 * Appkit's Router compiles routes through Symfony's
 * {@see CompiledUrlMatcherDumper} and writes the result to
 * `url_matching_routes.php` whenever a `cache_dir` is configured. That dumper
 * exports route defaults as PHP source, so **a closure or an object in a
 * default cannot be cached** — a loader that puts one there works in
 * development and dies the moment routes are compiled.
 *
 * This loader keeps that safe by storing only strings: the controller as
 * `[class, method]`, the resource class and operation as names, roles as
 * nested string arrays. `PanelResourceOptions` is read to compute them and
 * never stored. These tests pin that, so adding a closure default later is a
 * red test rather than a production incident.
 */
final class RouteDumpingTest extends TestCase
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

    private function routes(): RouteCollection
    {
        $file = tempnam(sys_get_temp_dir(), 'panel_dump_') . '.php';
        file_put_contents($file, "<?php\n\nuse " . PanelResourceConfigurator::class . ";\n\n"
            . 'return function (PanelResourceConfigurator $panel): void {'
            . '$panel->resource(\\' . WritableResource::class . '::class)->roles([\'ROLE_ADMIN\']);'
            . '$panel->resource(\\' . ReadOnlyResource::class . '::class);'
            . "};\n");
        $this->tempFiles[] = $file;

        $locator = new class ($file) implements FileLocatorInterface {
            public function __construct(private readonly string $file) {}

            public function locate(string $name, ?string $currentPath = null, bool $first = true): string
            {
                return $this->file;
            }
        };

        return (new PanelResourceRouteLoader($locator, FixtureController::class, static function (string $class): PanelResource {
            /** @var class-string<PanelResource> $class */
            return new $class();
        }))
            ->load($file, 'panel_resource');
    }

    /** Recursively: every default must be a scalar or an array of scalars. */
    private function assertExportable(mixed $value, string $path): void
    {
        if (is_scalar($value) || $value === null) {
            return;
        }

        self::assertIsArray(
            $value,
            sprintf('%s is a %s — route defaults must be exportable to cached PHP.', $path, get_debug_type($value)),
        );

        foreach ($value as $key => $item) {
            $this->assertExportable($item, $path . '[' . $key . ']');
        }
    }

    public function testNoRouteCarriesAClosureOrObjectInItsDefaults(): void
    {
        foreach ($this->routes()->all() as $name => $route) {
            foreach ($route->getDefaults() as $key => $value) {
                $this->assertExportable($value, sprintf('%s default "%s"', $name, $key));
            }

            foreach ($route->getRequirements() as $key => $value) {
                $this->assertExportable($value, sprintf('%s requirement "%s"', $name, $key));
            }

            foreach ($route->getOptions() as $key => $value) {
                $this->assertExportable($value, sprintf('%s option "%s"', $name, $key));
            }
        }
    }

    /** The matcher dump is what a cached kernel actually matches against. */
    public function testTheCollectionCompilesToAMatcherThatRoundTrips(): void
    {
        $dumped = (new CompiledUrlMatcherDumper($this->routes()))->dump();

        $file = tempnam(sys_get_temp_dir(), 'panel_matcher_') . '.php';
        file_put_contents($file, $dumped);
        $this->tempFiles[] = $file;

        $compiled = include $file;

        self::assertIsArray($compiled, 'The dumped matcher must be includable PHP returning an array.');

        // Asserted against the compiled structure rather than the source: the
        // dumper escapes backslashes, so a class name never appears literally.
        $flattened = $this->flatten($compiled);

        // The controller reference is what proves defaults survived; static
        // paths are compiled into a prefix tree rather than kept as values.
        self::assertContains(FixtureController::class, $flattened);
        self::assertContains('show', $flattened, 'The operation default must survive compilation.');
    }

    /**
     * Every scalar in a nested array, so an assertion can look for a value
     * without knowing the compiled layout.
     *
     * @param  array<mixed> $values
     * @return list<mixed>
     */
    private function flatten(array $values): array
    {
        $flat = [];

        array_walk_recursive($values, static function (mixed $value) use (&$flat): void {
            $flat[] = $value;
        });

        return $flat;
    }

    public function testTheCollectionCompilesToAGeneratorThatRoundTrips(): void
    {
        $dumped = (new CompiledUrlGeneratorDumper($this->routes()))->dump();

        $file = tempnam(sys_get_temp_dir(), 'panel_generator_') . '.php';
        file_put_contents($file, $dumped);
        $this->tempFiles[] = $file;

        $compiled = include $file;

        self::assertIsArray($compiled);
        self::assertArrayHasKey('actors_show', $compiled, 'Every generated route must survive the dump.');
        self::assertArrayHasKey('events', $compiled);
    }

    /**
     * Roles are the one nested structure in a default, and the kernel reads
     * them out of the *cached* route — so they must survive compilation with
     * their shape intact.
     */
    public function testRolesSurviveTheGeneratorDump(): void
    {
        $dumped = (new CompiledUrlMatcherDumper($this->routes()))->dump();

        self::assertStringContainsString('_is_granted_roles', $dumped);
        self::assertStringContainsString('ROLE_ADMIN', $dumped);
        self::assertStringNotContainsString('Closure', $dumped);
    }
}
