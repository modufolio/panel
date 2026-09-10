<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Resource;

use Modufolio\Appkit\Security\User\UserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The endpoint a drawer field's picker posts to, for a field declared
 * `pickable` on {@see DrawerTab::fields()}.
 *
 * The read-only drawer grid shows a record's own values; a `pickable` field
 * is the one exception that may be written to in place — a cover image
 * chosen without leaving the drawer for the full edit form. As with
 * {@see RelationAddUrls}, the frame carries the destination itself rather
 * than the client composing one from the page's own resource, which breaks
 * the moment the frame belongs to a *different* resource than the page (a
 * stacked drawer).
 *
 * Every pickable field now carries `pickUrl`, resolved from the record's own
 * `{key}_relation_store` route — the same endpoint an addable list already
 * posts to, keyed by the target field. No route (the field names one the
 * resource has none) or no permission (setting the field is editing the
 * record it hangs off, so this asks {@see Permissions::edit()} and
 * {@see Permissions::writable()} — the same questions the endpoint asks) and
 * the field carries no `pickUrl`, so it renders as a plain, non-interactive
 * placeholder: an offer the server would turn away is a broken promise, not
 * a shortcut.
 */
final class FieldPickUrls
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * The same tabs, with `pickUrl` on every field that can be picked and
     * `pickTarget` dropped from every field that cannot.
     *
     * @param  list<array<string, mixed>> $tabs   as {@see DrawerTab::collect()} built them
     * @param  object                     $entity the record the fields hang off
     * @param  object|null                $user   the viewer, for the permission check
     * @return list<array<string, mixed>>
     */
    public function stamp(array $tabs, PanelResource $resource, object $entity, ?UserInterface $user): array
    {
        $mayEdit = $resource->permissions()->edit($entity, $user);
        $params  = $resource->recordRouteParams($entity);
        $route   = $resource->key() . '_relation_store';

        return array_map(
            function (array $tab) use ($resource, $entity, $user, $mayEdit, $params, $route): array {
                $tab = $this->stampOne($tab, $resource, $entity, $user, $mayEdit, $params, $route);

                if (!isset($tab['sections']) || !is_array($tab['sections'])) {
                    return $tab;
                }

                $tab['sections'] = array_map(
                    fn (array $section): array => $this->stampOne($section, $resource, $entity, $user, $mayEdit, $params, $route),
                    $tab['sections'],
                );

                return $tab;
            },
            $tabs,
        );
    }

    /**
     * @param  array<string, mixed>      $tab
     * @param  array<string, string|int> $params
     * @return array<string, mixed>
     */
    private function stampOne(array $tab, PanelResource $resource, object $entity, ?UserInterface $user, bool $mayEdit, array $params, string $route): array
    {
        if (!isset($tab['fields']) || !is_array($tab['fields'])) {
            return $tab;
        }

        $tab['fields'] = array_map(
            function (mixed $field) use ($resource, $entity, $user, $mayEdit, $params, $route): mixed {
                if (!is_array($field) || !isset($field['pickTarget']) || !is_string($field['pickTarget'])) {
                    return $field;
                }

                $target = $field['pickTarget'];
                $url    = $mayEdit && $resource->permissions()->writable($target, $user, $entity)
                    ? $this->url($route, [...$params, 'field' => $target])
                    : null;

                if ($url === null) {
                    unset($field['pickTarget'], $field['pickLabel']);

                    return $field;
                }

                return [...$field, 'pickUrl' => $url];
            },
            $tab['fields'],
        );

        return $tab;
    }

    /** @param array<string, string|int> $params */
    private function url(string $name, array $params): ?string
    {
        try {
            return $this->urlGenerator->generate($name, $params);
        } catch (\Throwable) {
            return null;
        }
    }
}
