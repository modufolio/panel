<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

/**
 * Collects the resources whose panel routes are generated.
 *
 * Mirrors JsonApiConfigurator, including its fluent per-resource builder:
 * config/panel_resources.php receives one of these and registers resource
 * classes on it; PanelResourceRouteLoader turns the result into a
 * RouteCollection.
 *
 *     return function (PanelResourceConfigurator $panel): void {
 *         $panel->resource(MovieResource::class)
 *             ->only(['index'])
 *             ->prefix('/panel/library');
 *
 *         // or, when the defaults are all you need:
 *         $panel->resources([BookResource::class, AlbumReviewResource::class]);
 *
 *         // or every resource class under a directory:
 *         $panel->discover(__DIR__ . '/../src/Panel', 'App\\Panel');
 *     };
 *
 * Which roles reach a resource and where it sits in the menu are the
 * resource's own declarations — {@see PanelResource::permissions()} and
 * {@see PanelResource::menu()} — not registration options: registration says
 * which routes exist, the resource says everything about itself.
 */
final class PanelResourceConfigurator
{
    /**
     * Operations the loader knows how to generate.
     *
     * index/show always come from the resource declaration alone; the write
     * trio additionally needs the resource to declare `form()` — without
     * a form there is nothing to render or validate, so the loader skips them.
     */
    public const OPERATIONS = ['index', 'show', 'create', 'edit', 'delete'];

    /** @var array<class-string<PanelResource>, PanelResourceOptions> */
    private array $resources = [];

    /**
     * Register one resource and return its options for further configuration.
     *
     * @param class-string<PanelResource> $resourceClass
     */
    public function resource(string $resourceClass): PanelResourceOptions
    {
        return $this->resources[$resourceClass] ??= new PanelResourceOptions();
    }

    /**
     * Register several resources with the default options.
     *
     * @param list<class-string<PanelResource>> $resourceClasses
     */
    public function resources(array $resourceClasses): self
    {
        foreach ($resourceClasses as $resourceClass) {
            $this->resource($resourceClass);
        }

        return $this;
    }

    /**
     * Register every concrete {@see PanelResource} subclass found under a
     * directory, with the default options.
     *
     * Files map to classes by PSR-4: `$directory/Admin/EventResource.php` is
     * `$namespace\Admin\EventResource`. A file whose class does not exist,
     * is abstract, or is not a resource is skipped without comment — a
     * directory of resources usually holds a permissions class or two beside
     * them. A resource that must *not* get generated routes (one served by a
     * hand-written controller) belongs in another directory, or is registered
     * by the explicit list instead.
     *
     * @param string $directory absolute path
     * @param string $namespace the namespace that directory is the root of
     */
    public function discover(string $directory, string $namespace): self
    {
        $root = rtrim($directory, '/');

        if (!is_dir($root)) {
            throw new \InvalidArgumentException(sprintf('discover(): "%s" is not a directory.', $directory));
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $classes = [];

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $class    = rtrim($namespace, '\\') . '\\' . str_replace('/', '\\', $relative);

            if (!class_exists($class) || !is_subclass_of($class, PanelResource::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        // Alphabetical, so the generated routes and the menu do not depend on
        // the order a filesystem happens to list files in.
        sort($classes);

        return $this->resources($classes);
    }

    /**
     * @return array<class-string<PanelResource>, PanelResourceOptions>
     */
    public function buildConfig(): array
    {
        return $this->resources;
    }
}

/**
 * Per-resource route options.
 *
 * Fluent and mutable, like JsonApi's ResourceConfigurator — the loader reads
 * the finished object, so ordering of the calls does not matter.
 */
final class PanelResourceOptions
{
    /** @var list<string> */
    private array $operations = PanelResourceConfigurator::OPERATIONS;

    private ?string $prefix = null;

    /**
     * Generate only these operations.
     *
     * @param list<string> $operations
     */
    public function only(array $operations): self
    {
        $this->operations = array_values(array_intersect(
            PanelResourceConfigurator::OPERATIONS,
            $operations,
        ));

        return $this;
    }

    /**
     * Generate everything except these operations.
     *
     * @param list<string> $operations
     */
    public function except(array $operations): self
    {
        $this->operations = array_values(array_diff(
            PanelResourceConfigurator::OPERATIONS,
            $operations,
        ));

        return $this;
    }

    /** Mount the resource somewhere other than `/panel`. */
    public function prefix(string $prefix): self
    {
        $this->prefix = rtrim($prefix, '/');

        return $this;
    }

    public function generates(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }

    public function prefixOr(string $default): string
    {
        return $this->prefix ?? $default;
    }
}
