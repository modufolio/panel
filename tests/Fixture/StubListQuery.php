<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Query\ListQueryInterface;

/**
 * A list query that exists only to be named. Resources under test must point
 * `listQueryClass()` at a real implementation, and none of them ever run it.
 */
final class StubListQuery implements ListQueryInterface
{
    public function sortable(): array
    {
        return [];
    }

    public function defaultOrder(): array
    {
        return [];
    }

    public function mapSort(string $field): ?string
    {
        return null;
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        return $qb;
    }

    public function forCount(QueryBuilder $qb): QueryBuilder
    {
        return $qb;
    }
}
