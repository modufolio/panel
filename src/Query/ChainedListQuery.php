<?php

declare(strict_types=1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * A list query with more objects chained onto it — what a resource's
 * `queries()` hook adds to the derived query, or to its class.
 *
 * The extras narrow both the rows and the count, because a predicate applied
 * to one and not the other advertises rows that do not exist. The sort
 * contract is the base query's, untouched.
 */
final class ChainedListQuery extends AbstractQuery implements ListQueryInterface
{
    /**
     * @param list<QueryInterface> $extras
     */
    public function __construct(
        private readonly ListQueryInterface $base,
        private readonly array $extras,
    ) {
    }

    public function sortable(): array
    {
        return $this->base->sortable();
    }

    public function defaultOrder(): array
    {
        return $this->base->defaultOrder();
    }

    public function mapSort(string $field): ?string
    {
        return $this->base->mapSort($field);
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        return $this->chain($this->base->apply($qb), $this->extras);
    }

    public function forCount(QueryBuilder $qb): QueryBuilder
    {
        return $this->chain($this->base->forCount($qb), $this->extras);
    }
}
