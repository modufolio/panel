<?php

declare(strict_types=1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Contract for a resource's list query.
 *
 * Implementations are the single source of truth for *how a resource is
 * listed*: which fields may be sorted on, how virtual field names map onto
 * entity properties, and what the default ordering is.
 *
 * PanelResource and TableSchema read all of that from here, so a resource
 * never repeats its sortable-field allowlist.
 *
 * Implementations must accept the following constructor signature (named
 * arguments), which is how PanelResource::buildListQuery() builds them:
 *
 *     __construct(
 *         ?string $search = null,
 *         ?string $trashed = null,
 *         ?array  $sort = null,
 *         ?int    $limit = null,
 *         ?int    $offset = null,
 *     )
 */
interface ListQueryInterface extends QueryInterface
{
    /**
     * Entity property names that may be sorted on.
     *
     * Values are interpolated into DQL by the keyset navigation queries, so
     * this must only ever contain hardcoded property names.
     *
     * @return list<string>
     */
    public static function sortableFields(): array;

    /**
     * Default ordering when the request carries no usable sort param.
     *
     * @return array<string, 'ASC'|'DESC'> single entry, e.g. ['lastName' => 'ASC']
     */
    public static function defaultSort(): array;

    /**
     * Translate a public/virtual sort field onto an entity property.
     *
     * Returns null when the field is not sortable, so callers can silently
     * ignore it rather than leaking schema information.
     */
    public static function mapSortField(string $field): ?string;

    /**
     * Build the count query — same filters, no ordering or pagination.
     */
    public function forCount(QueryBuilder $qb): QueryBuilder;
}
