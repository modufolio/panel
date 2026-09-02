<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Query\AbstractListQuery;

/**
 * The list query a consuming application would write: a sortable allowlist,
 * one public column name that differs from its property, a search predicate,
 * and the soft-delete scope that `trashed` selects.
 */
final class MovieListQuery extends AbstractListQuery
{
    protected const SORTABLE_FIELDS = ['title', 'year', 'rating', 'createdAt'];

    protected const FIELD_MAPPING = ['created_at' => 'createdAt'];

    public static function defaultSort(): array
    {
        return ['title' => 'ASC'];
    }

    protected function applyFilters(QueryBuilder $qb): QueryBuilder
    {
        $alias = $this->getRootAlias($qb);

        if ($this->search !== null && $this->search !== '') {
            $qb->andWhere($qb->expr()->like("LOWER({$alias}.title)", ':search'))
                ->setParameter('search', '%' . mb_strtolower($this->search) . '%');
        }

        // The predicate Filter::trashed() declares the control for: absent
        // means live rows only, `with` lifts the scope, `only` inverts it.
        match ($this->trashed) {
            'with'  => null,
            'only'  => $qb->andWhere("{$alias}.deletedAt IS NOT NULL"),
            default => $qb->andWhere("{$alias}.deletedAt IS NULL"),
        };

        return $qb;
    }
}
