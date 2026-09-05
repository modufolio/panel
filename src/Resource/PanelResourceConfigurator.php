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
 *             ->menu('Movies', icon: 'film', group: 'Library', order: 10)
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

    private ?string $prefix = null;

    /** @var array{label: string, icon: string|null, group: string|null, order: int}|null */
    private ?array $menu = null;

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

    /**
     * Put the resource in the panel's menu.
     *
     * The menu is part of a resource's public identity, so it is declared
     * where the resource is registered rather than in a file of its own that
     * nothing errors for forgetting. The loader stores it on the index route
     * as `_panel_menu`; {@see \Modufolio\Panel\Routing\ResourceMenu} reads
     * every such route back, and the host's navigation renders them beside
     * whatever hand-written entries it has. The route's own roles gate the
     * entry — there is no second role list to keep in step.
     *
     * @param string      $label what the sidebar says
     * @param string|null $icon  an icon name the panel's `<Icon>` knows
     * @param string|null $group the heading the entry sits under
     * @param int         $order position within the menu; lower comes first
     */
    public function menu(string $label, ?string $icon = null, ?string $group = null, int $order = 50): self
    {
        if (trim($label) === '') {
            throw new \InvalidArgumentException('menu(): a menu entry needs a label.');
        }

        $this->menu = ['label' => $label, 'icon' => $icon, 'group' => $group, 'order' => $order];

        return $this;
    }

    /** @return array{label: string, icon: string|null, group: string|null, order: int}|null */
    public function menuItem(): ?array
    {
        return $this->menu;
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
