<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

/**
 * The derived movies (title and studio searchable), opted into the panel's
 * search across resources, with the year as the hit's detail.
 */
final class SearchableMovieResource extends DerivedMovieResource
{
    public function searchableGlobally(): bool
    {
        return true;
    }

    public function globalSearchDetails(array $row): array
    {
        return isset($row['year']) ? [(string) $row['year']] : [];
    }
}
