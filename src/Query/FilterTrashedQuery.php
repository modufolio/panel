<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

final class FilterTrashedQuery extends AbstractQuery
{
    public function __construct(
        private readonly ?string $mode = null
    ) {
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        $alias = $this->getRootAlias($qb);

        if ($this->mode === 'only') {

            return $qb->andWhere($qb->expr()->isNotNull("{$alias}.deletedAt"));
        }

        if ($this->mode === 'with') {

            return $qb;
        }


        return $qb->andWhere($qb->expr()->isNull("{$alias}.deletedAt"));
    }
}
