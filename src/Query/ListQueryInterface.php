<?php

declare(strict_types=1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * The query behind a listing: what narrows the rows, how they are ordered,
 * and which orderings the request may ask for.
 *
 * Instance methods throughout, so a query can be derived per resource from
 * its table rather than declared once per class in constants — and so the
 * listing can ask the object it is about to apply, never a class name.
 */
interface ListQueryInterface extends QueryInterface
{
    /**
     * Entity properties the request may sort on.
     *
     * Interpolated into DQL by the keyset navigation queries, so these must
     * only ever be declared property names — never anything a request sent.
     *
     * @return list<string>
     */
    public function sortable(): array;

    /**
     * The order applied when the request names none: one `field => ASC|DESC`.
     *
     * @return array<string, string>
     */
    public function defaultOrder(): array;

    /** The entity property a public sort key maps to, or null when it cannot be sorted on. */
    public function mapSort(string $field): ?string;

    /** The query's predicates without its order, limit or offset — what the count and the aggregates share. */
    public function forCount(QueryBuilder $qb): QueryBuilder;
}
