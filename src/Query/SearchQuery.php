<?php

declare(strict_types=1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * A case-insensitive `LIKE` across the given paths, OR-ed.
 *
 * A path is an entity property (`title`) or one step into a to-one relation
 * (`studio.name`), which is left-joined under a stable alias so the listing
 * and its count share one predicate. A to-one join cannot multiply rows, so
 * the count stays correct; a to-many path would, and is refused.
 *
 * One of the objects {@see DerivedListQuery} chains; usable by hand in a
 * list query class exactly like {@see FilterTrashedQuery}.
 */
final class SearchQuery extends AbstractQuery
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        private readonly ?string $term,
        private readonly array $paths,
    ) {
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        $term = trim((string) $this->term);

        if ($term === '' || $this->paths === []) {
            return $qb;
        }

        $alias = $this->getRootAlias($qb);
        $or    = $qb->expr()->orX();

        foreach ($this->paths as $path) {
            $or->add($qb->expr()->like('LOWER(' . $this->column($qb, $alias, $path) . ')', ':search'));
        }

        return $qb->andWhere($or)->setParameter('search', '%' . mb_strtolower($term) . '%');
    }

    /** The DQL column for a path, joining the relation it crosses once. */
    private function column(QueryBuilder $qb, string $alias, string $path): string
    {
        $segments = explode('.', $path);

        if (count($segments) === 1) {
            return "{$alias}.{$path}";
        }

        if (count($segments) > 2) {
            throw new \InvalidArgumentException(sprintf(
                'SearchQuery: "%s" crosses more than one relation; search paths reach one step into a to-one relation.',
                $path,
            ));
        }

        [$relation, $field] = $segments;
        $joinAlias          = 'search_' . $relation;

        if (!in_array($joinAlias, $qb->getAllAliases(), true)) {
            $qb->leftJoin("{$alias}.{$relation}", $joinAlias);
        }

        return "{$joinAlias}.{$field}";
    }
}
