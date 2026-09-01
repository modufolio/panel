<?php

declare(strict_types=1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Shared plumbing for composable queries: the root alias every predicate
 * needs, and a chain() to apply a list of queries in order.
 */
abstract class AbstractQuery implements QueryInterface
{
    protected function getRootAlias(QueryBuilder $qb): string
    {
        $aliases = $qb->getRootAliases();

        if (empty($aliases)) {
            throw new \RuntimeException('QueryBuilder has no root alias. Did you forget to call from()?');
        }

        return $aliases[0];
    }

    /**
     * Not typed list<QueryInterface> on purpose: callers hand in arrays built
     * from config, and the runtime check below is the enforcement.
     *
     * @param list<mixed> $queries
     */
    protected function chain(QueryBuilder $qb, array $queries): QueryBuilder
    {
        foreach ($queries as $query) {
            if (!$query instanceof QueryInterface) {
                throw new \InvalidArgumentException('All queries must implement QueryInterface');
            }

            $qb = $query->apply($qb);
        }

        return $qb;
    }
}
