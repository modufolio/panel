<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field;

use Doctrine\ORM\QueryBuilder;

/**
 * A field type that knows how it filters.
 *
 * The type declares a closed map of named operators and how each becomes a
 * predicate — so a listing can build its filter menu from the declaration
 * and the server maps operator + value onto the query in one place, instead
 * of every resource hand-writing both halves. Unknown operators throw:
 * a filter the type does not declare is a caller bug, not a no-op.
 */
interface FilterableFieldInterface
{
    /**
     * @return array<string, string> operator key => human label, in menu order
     */
    public static function filterOperators(): array;

    /**
     * Apply one declared operator as an AND-predicate on $dqlField
     * (an aliased path like `p.title`). $parameter names the bound
     * parameter; the caller guarantees its uniqueness within the query.
     */
    public static function applyFilter(QueryBuilder $qb, string $dqlField, string $operator, mixed $value, string $parameter): void;
}
