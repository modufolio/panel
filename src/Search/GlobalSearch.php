<?php

declare(strict_types=1);

namespace Modufolio\Panel\Search;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Contracts\ResourceLocatorInterface;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Resource\ResourceListing;
use Modufolio\Panel\Support\Label;
use Modufolio\Psr7\Http\ServerRequest;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * One search across every resource that opted in: each is asked the way
 * its own listing would be — the same searchable columns, the same scope,
 * the same rows — limited to a few hits, and answered with a title, some
 * details and the record's URL, grouped by resource.
 *
 * Opt-in, bounded and permission-aware by construction: a resource takes
 * part only when {@see PanelResource::searchableGlobally()} says so, only
 * for a viewer its permissions let view the type, and only for the rows its
 * scope shows that viewer.
 */
final class GlobalSearch
{
    public const DEFAULT_LIMIT = 5;

    /**
     * @param list<class-string<PanelResource>>|\Closure(): list<class-string<PanelResource>> $resourceClasses
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ResourceLocatorInterface $resources,
        private readonly array|\Closure $resourceClasses,
    ) {
    }

    /**
     * @return array{query: string, groups: list<array{key: string, label: string, total: int, items: list<array{title: string, details: list<string>, href: string}>}>}
     */
    public function search(string $query, ?UserInterface $user, int $limit = self::DEFAULT_LIMIT): array
    {
        $query = trim($query);
        $groups = [];

        if ($query === '') {
            return ['query' => $query, 'groups' => []];
        }

        foreach ($this->classes() as $class) {
            $resource = $this->resources->get($class);

            if (!$resource->searchableGlobally() || !$resource->permissions()->view(null, $user)) {
                continue;
            }

            $group = $this->searchResource($resource, $query, $user, $limit);

            if ($group['items'] !== []) {
                $groups[] = $group;
            }
        }

        return ['query' => $query, 'groups' => $groups];
    }

    /**
     * @return array{key: string, label: string, total: int, items: list<array{title: string, details: list<string>, href: string}>}
     */
    private function searchResource(PanelResource $resource, string $query, ?UserInterface $user, int $limit): array
    {
        $key = $resource->key();
        $request = new ServerRequest('GET', sprintf('/%s?%s', $key, http_build_query(['search' => $query, 'page' => ['size' => $limit]])));

        $props = (new ResourceListing($resource, $request, $this->entityManager, $this->urlGenerator, $user))->render()->props();

        /** @var array{data?: list<array<string, mixed>>, meta?: array{total?: int}} $collection */
        $collection = $props[$key] ?? [];
        /** @var array{urls?: array<string, string|null>} $resourceProps */
        $resourceProps = $props['resource'] ?? [];
        $template = $resourceProps['urls']['show'] ?? null;
        $items = [];

        foreach ($collection['data'] ?? [] as $row) {
            $href = $template === null ? null : self::fill($template, $row);

            if ($href === null) {
                continue;
            }

            $items[] = [
                'title'   => $resource->globalSearchTitle($row),
                'details' => $resource->globalSearchDetails($row),
                'href'    => $href,
            ];
        }

        return [
            'key'   => $key,
            'label' => Label::sentence($key),
            'total' => (int) ($collection['meta']['total'] ?? count($items)),
            'items' => $items,
        ];
    }

    /**
     * `{id}` in a route template takes the row's public id, as the client fills it.
     *
     * @param array<string, mixed> $row
     */
    private static function fill(string $template, array $row): ?string
    {
        $id = $row['uuid'] ?? $row['id'] ?? null;

        if (!is_scalar($id) || (string) $id === '') {
            return null;
        }

        return str_replace('{id}', rawurlencode((string) $id), $template);
    }

    /** @return list<class-string<PanelResource>> */
    private function classes(): array
    {
        return $this->resourceClasses instanceof \Closure ? ($this->resourceClasses)() : $this->resourceClasses;
    }
}
