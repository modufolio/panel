<?php

declare(strict_types=1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Shared machinery for a resource's list query.
 *
 * Every list query answered the same four questions in the same way — which
 * fields are sortable, how a virtual column name maps onto an entity property,
 * how the sort/limit/offset are applied, and how the count query reuses the
 * listing's filters. Only the *answers* differ per resource, so subclasses now
 * declare just those:
 *
 *  - `SORTABLE_FIELDS` / `FIELD_MAPPING` constants,
 *  - `defaultSort()`,
 *  - `applyFilters()` — the predicates shared by the listing and its count,
 *  - optionally `applyEagerLoads()` for list-only joins/selects.
 *
 * Two drift hazards are closed by construction here:
 *
 *  1. `apply()` derives its fallback ordering from `defaultSort()` instead of
 *     repeating it as literals, so the two cannot disagree.
 *  2. `forCount()` runs exactly `applyFilters()`, so a filter can never reach
 *     the listing without also reaching the count — which would report a total
 *     the page itself contradicts.
 */
abstract class AbstractListQuery extends AbstractQuery implements ListQueryInterface
{
    /**
     * Entity properties that may be sorted on.
     *
     * Interpolated into DQL by the keyset navigation queries, so this must only
     * ever contain hardcoded property names.
     *
     * @var list<string>
     */
    protected const SORTABLE_FIELDS = [];

    /**
     * Virtual/table column name => entity property, for columns whose public
     * name is not the property name (e.g. `created_at` => `createdAt`).
     *
     * @var array<string, string>
     */
    protected const FIELD_MAPPING = [];

    public function __construct(
        protected readonly ?string $search = null,
        protected readonly ?string $trashed = null,
        protected readonly ?array $sort = null,
        protected readonly ?int $limit = null,
        protected readonly ?int $offset = null,
    ) {
    }

    public static function sortableFields(): array
    {
        return static::SORTABLE_FIELDS;
    }

    public static function mapSortField(string $field): ?string
    {
        $mapped = static::FIELD_MAPPING[$field] ?? $field;

        return in_array($mapped, static::SORTABLE_FIELDS, true) ? $mapped : null;
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        $qb = $this->applyFilters($qb);
        $qb = $this->applyEagerLoads($qb);

        // Derived from defaultSort() rather than restated, so the interface's
        // declared default and the one actually applied cannot drift apart.
        $default        = static::defaultSort();
        $defaultField   = array_key_first($default);
        $defaultDirection = $defaultField !== null ? $default[$defaultField] : 'ASC';

        $qb = (new SortQuery(
            $this->mapSortFields($this->sort ?? []),
            defaultField: $defaultField ?? 'id',
            defaultDirection: $defaultDirection,
        ))->apply($qb);

        if ($this->limit !== null) {
            $qb->setMaxResults($this->limit);
        }

        if ($this->offset !== null) {
            $qb->setFirstResult($this->offset);
        }

        return $qb;
    }

    public function forCount(QueryBuilder $qb): QueryBuilder
    {
        return $this->applyFilters($qb);
    }

    /**
     * Predicates shared by the listing and its count.
     *
     * Anything that narrows the result set belongs here — never in `apply()`
     * alone, or the total will contradict the rows.
     */
    abstract protected function applyFilters(QueryBuilder $qb): QueryBuilder;

    /**
     * Joins/selects that exist only to avoid N+1 on the listing.
     *
     * Deliberately not part of `applyFilters()`: the count query has no rows to
     * hydrate, so it should not pay for the select.
     */
    protected function applyEagerLoads(QueryBuilder $qb): QueryBuilder
    {
        return $qb;
    }

    /**
     * Validate the requested sort against the allowlist, dropping unknown
     * fields silently rather than leaking which properties exist.
     *
     * @param  array<string, string> $sort
     * @return array<string, string>
     */
    protected function mapSortFields(array $sort): array
    {
        $mapped = [];

        foreach ($sort as $field => $direction) {
            if (($mappedField = static::mapSortField($field)) === null) {
                continue;
            }

            $mapped[$mappedField] = $direction;
        }

        return $mapped;
    }
}
