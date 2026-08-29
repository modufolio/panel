<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

/**
 * A filter's choices, sourced from a related entity.
 *
 * Held as data rather than resolved eagerly so a TableSchema stays a pure
 * value object; ResourceListing (which has the EntityManager) turns this into
 * a flat option list at serialisation time.
 */
final readonly class RelationOptions
{
    /**
     * Above this many rows, a relation stops shipping its whole table to the
     * client and is searched on the server instead. Chosen to be comfortably
     * larger than any list a human would scroll (statuses, studios, tags) and
     * far smaller than any table where sending everything would hurt.
     */
    public const AUTO_SEARCH_THRESHOLD = 100;

    /** Rows one search returns. The cap is reported to the client, not hidden. */
    public const SEARCH_LIMIT = 50;

    /**
     * @param class-string $entityClass
     * @param bool|null    $searchable Force server-side search on (true) or off
     *                                 (false); null decides by row count, which
     *                                 is what a guessed relation gets.
     */
    public function __construct(
        public string $entityClass,
        public string $labelField,
        public string $valueField = 'id',
        public ?bool $searchable = null,
    ) {
    }
}
