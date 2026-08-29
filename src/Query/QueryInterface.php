<?php

declare(strict_types=1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Anything that narrows a query builder.
 *
 * Deliberately one method: the panel composes these, it does not inspect them.
 */
interface QueryInterface
{
    public function apply(QueryBuilder $qb): QueryBuilder;
}
