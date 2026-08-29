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
 *             ->roles(['ROLE_ADMIN'])
 *             ->prefix('/panel/library');
 *
 *         // or, when the defaults are all you need:
 *         $panel->resources([BookResource::class, AlbumReviewResource::class]);
 *     };
 */
final class PanelResourceConfigurator
{
    /**
     * Operations the loader knows how to generate.
     *
     * index/show always come from the resource declaration alone; the write
     * trio additionally needs the resource to declare `formFields()` — without
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

    /** @var list<string> */
    private array $roles = [];

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

    /**
     * Roles allowed to reach this resource, any one of which suffices.
     *
     * Stored on the route as `_is_granted_roles`, the same default
     * #[IsGranted] writes, so the kernel enforces it with the role hierarchy
     * before the controller runs — a generated resource is guarded exactly
     * like a hand-written one.
     *
     * @param list<string> $roles
     */
    public function roles(array $roles): self
    {
        $this->roles = array_values(array_filter($roles, static fn (string $r): bool => $r !== ''));

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

    /**
     * The route default the kernel enforces: a list of AND-ed groups, each an
     * OR-ed role list. One group here — any declared role grants access.
     *
     * @return list<list<string>>
     */
    public function roleGroups(): array
    {
        return $this->roles === [] ? [] : [$this->roles];
    }

    public function prefixOr(string $default): string
    {
        return $this->prefix ?? $default;
    }
}
