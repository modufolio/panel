<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

final class SortQuery extends AbstractQuery
{
    /**
     * @param array<string, string> $sort         Column => direction
     * @param array<string, string> $fieldMapping Public column name => entity property
     */
    public function __construct(
        private readonly array $sort = [],
        private readonly ?string $defaultField = null,
        private readonly string $defaultDirection = 'ASC',
        private readonly array $fieldMapping = []
    ) {
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        $alias = $this->getRootAlias($qb);

        if (empty($this->sort)) {

            if ($this->defaultField !== null) {
                $qb->addOrderBy(
                    "{$alias}.{$this->defaultField}",
                    $this->defaultDirection
                );
            }

            return $qb;
        }


        foreach ($this->sort as $field => $direction) {

            $direction = strtoupper($direction);
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                $direction = 'ASC';
            }


            if (isset($this->fieldMapping[$field])) {

                $orderByField = "{$alias}.{$this->fieldMapping[$field]}";
            } else {

                $convertedField = lcfirst(str_replace('_', '', ucwords($field, '_')));
                $orderByField = "{$alias}.{$convertedField}";
            }

            $qb->addOrderBy($orderByField, $direction);
        }

        return $qb;
    }
}
