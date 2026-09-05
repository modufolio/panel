<?php

declare(strict_types=1);

namespace Modufolio\Panel\Routing;

use Symfony\Component\Routing\RouteCollection;

/**
 * The menu entries the generated routes declare.
 *
 * A resource registered with `->menu(...)` has its entry stored on its index
 * route, under {@see self::DEFAULT}, by {@see PanelResourceRouteLoader}. The
 * host's navigation asks here for all of them and renders them beside its
 * own hand-written entries; the panel itself renders nothing. Each entry
 * names the route to link to and the roles that gate it — the same roles the
 * kernel enforces on the route, flattened to a single any-of list, so the
 * menu can never admit what the route refuses or hide what it admits.
 */
final class ResourceMenu
{
    public const DEFAULT = '_panel_menu';

    /**
     * Every declared entry, in route order. Sorting is the host's: it has
     * hand-written entries of its own to interleave.
     *
     * @return list<array{route: string, label: string, icon: string|null, group: string|null, order: int, roles: list<string>}>
     */
    public static function fromRoutes(RouteCollection $routes): array
    {
        $entries = [];

        foreach ($routes->all() as $name => $route) {
            $menu = $route->getDefault(self::DEFAULT);

            if (!is_array($menu) || !is_string($menu['label'] ?? null)) {
                continue;
            }

            $roles = [];
            foreach ((array) ($route->getDefault('_is_granted_roles') ?? []) as $group) {
                foreach ((array) $group as $role) {
                    if (is_string($role)) {
                        $roles[] = $role;
                    }
                }
            }

            $entries[] = [
                'route' => (string) $name,
                'label' => $menu['label'],
                'icon'  => is_string($menu['icon'] ?? null) ? $menu['icon'] : null,
                'group' => is_string($menu['group'] ?? null) ? $menu['group'] : null,
                'order' => is_int($menu['order'] ?? null) ? $menu['order'] : 50,
                'roles' => array_values(array_unique($roles)),
            ];
        }

        return $entries;
    }
}
