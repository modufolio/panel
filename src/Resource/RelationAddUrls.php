<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Resource;

use Modufolio\Appkit\Security\User\UserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The endpoint each addable list on a drawer frame posts to.
 *
 * A frame's tabs describe *what* a list holds; without this they never said
 * *where* a new row goes. The client filled that gap by composing the URL from
 * the page's own resource — which is right only while the frame belongs to the
 * page. Stack a film over an actor and the composed URL addressed the actors
 * endpoint, so the "+ Add" the film's own resource declared did nothing.
 *
 * Every addable list now carries `addUrl`, resolved from the record's own
 * `{key}_relation_store` route. No route (the resource generates no form
 * routes) or no permission (adding a row is editing the record it hangs off,
 * so this asks {@see Permissions::edit()} — the same question the endpoint
 * asks) and the list is not addable: an offer the server would turn away is a
 * broken promise, not a shortcut.
 */
final class RelationAddUrls
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * The same tabs, with `addUrl` on every list that can be added to and
     * `addable` off on every list that cannot.
     *
     * @param  list<array<string, mixed>> $tabs   as {@see PanelResource::drawerTabsFor()} built them
     * @param  object                     $entity the record the lists hang off
     * @param  object|null                $user   the viewer, for the permission check
     * @return list<array<string, mixed>>
     */
    public function stamp(array $tabs, PanelResource $resource, object $entity, ?UserInterface $user): array
    {
        $mayEdit = $resource->permissions()->edit($entity, $user);
        $params  = $resource->recordRouteParams($entity);
        $route   = $resource->key() . '_relation_store';

        return array_map(
            function (array $tab) use ($mayEdit, $params, $route): array {
                $tab = $this->stampOne($tab, $mayEdit, $params, $route);

                if (!isset($tab['sections']) || !is_array($tab['sections'])) {
                    return $tab;
                }

                $tab['sections'] = array_map(
                    fn (array $section): array => $this->stampOne($section, $mayEdit, $params, $route),
                    $tab['sections'],
                );

                return $tab;
            },
            $tabs,
        );
    }

    /**
     * @param  array<string, mixed>       $list
     * @param  array<string, string|int>  $params
     * @return array<string, mixed>
     */
    private function stampOne(array $list, bool $mayEdit, array $params, string $route): array
    {
        if (($list['addable'] ?? false) !== true) {
            return $list;
        }

        $target = $list['addTarget'] ?? null;
        $url    = $mayEdit && is_string($target) && $target !== ''
            ? $this->url($route, [...$params, 'field' => $target])
            : null;

        if ($url === null) {
            return [...$list, 'addable' => false, 'addUrl' => null];
        }

        return [...$list, 'addUrl' => $url];
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
