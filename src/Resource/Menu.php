<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

/**
 * A resource's place in the panel's menu.
 *
 *     public function menu(): Menu
 *     {
 *         return Menu::make('Events', icon: 'calendar', group: 'Main', order: 16);
 *     }
 *
 * Declared on the resource, because the menu is part of its public identity
 * and belongs where its key and its routes are declared. The loader stores it
 * on the generated index route, the one it links to, and the host's
 * navigation reads every entry back through {@see \Modufolio\Panel\Routing\ResourceMenu}
 * with the roles that route enforces — so there is no second role list to
 * keep in step. A resource without one still works; it is simply not linked
 * from the sidebar, which is right for a resource only reached from another's
 * drawer.
 */
final class Menu
{
    private function __construct(
        private readonly string $label,
        private readonly ?string $icon,
        private readonly ?string $group,
        private readonly int $order,
    ) {
    }

    /**
     * @param string      $label what the entry says
     * @param string|null $icon  a name from the panel's built-in set, or one the app registered
     * @param string|null $group the heading the entry sits under
     * @param int         $order position within the menu; lower comes first
     */
    public static function make(string $label, ?string $icon = null, ?string $group = null, int $order = 50): self
    {
        if (trim($label) === '') {
            throw new \InvalidArgumentException('Menu::make(): a menu entry needs a label.');
        }

        return new self($label, $icon, $group, $order);
    }

    public function icon(string $icon): self
    {
        return new self($this->label, $icon, $this->group, $this->order);
    }

    public function group(string $group): self
    {
        return new self($this->label, $this->icon, $group, $this->order);
    }

    public function order(int $order): self
    {
        return new self($this->label, $this->icon, $this->group, $order);
    }

    /** @return array{label: string, icon: string|null, group: string|null, order: int} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'icon' => $this->icon, 'group' => $this->group, 'order' => $this->order];
    }
}
