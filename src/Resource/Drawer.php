<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

/**
 * What the drawer shows for one record: its tabs, each a details grid or a
 * list of related rows.
 *
 *     Drawer::make()->tabs([
 *         DrawerTab::details()->fields(['title', 'starts_at', 'contact']),
 *         DrawerTab::relation('attendees', 'Attendees')->addable(),
 *     ]);
 *
 * The details tab is a *list of keys*: labels and formatting come from the
 * resource's {@see PanelResource::fields()} and its form, so the drawer shows
 * a subset of what the form edits without saying anything twice. Without a
 * key list the grid follows the form; without a form, the columns.
 */
final class Drawer
{
    /** @var list<DrawerTab> */
    private array $tabs = [];

    private function __construct()
    {
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * The tabs, in order. Replaces any declared before.
     *
     * @param list<DrawerTab> $tabs
     */
    public function tabs(array $tabs): self
    {
        $clone = clone $this;
        $clone->tabs = $tabs;

        return $clone;
    }

    /** @return list<DrawerTab> */
    public function declaredTabs(): array
    {
        return $this->tabs;
    }
}
