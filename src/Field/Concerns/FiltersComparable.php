<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field\Concerns;

use Doctrine\ORM\QueryBuilder;

/**
 * The ordered-value operator set. Operator keys match modufolio/json-api's
 * RangeFilter (gt/gte/lt/lte/between) — one filter vocabulary across the
 * ecosystem. `between` takes a two-element [from, to] array, both bounds
 * inclusive, exactly as the JSON:API layer's `from..to` form.
 */
trait FiltersComparable
{
    public static function filterOperators(): array
    {
        return [
            'equals' => 'Equals',
            'not_equals' => 'Does not equal',
            'gt' => 'Greater than',
            'gte' => 'At least',
            'lt' => 'Less than',
            'lte' => 'At most',
            'between' => 'Between',
            'empty' => 'Is empty',
            'not_empty' => 'Is not empty',
        ];
    }

    public static function applyFilter(QueryBuilder $qb, string $dqlField, string $operator, mixed $value, string $parameter): void
    {
        match ($operator) {
            'equals' => $qb->andWhere("{$dqlField} = :{$parameter}")->setParameter($parameter, $value),
            'not_equals' => $qb->andWhere("{$dqlField} != :{$parameter}")->setParameter($parameter, $value),
            'gt' => $qb->andWhere("{$dqlField} > :{$parameter}")->setParameter($parameter, $value),
            'gte' => $qb->andWhere("{$dqlField} >= :{$parameter}")->setParameter($parameter, $value),
            'lt' => $qb->andWhere("{$dqlField} < :{$parameter}")->setParameter($parameter, $value),
            'lte' => $qb->andWhere("{$dqlField} <= :{$parameter}")->setParameter($parameter, $value),
            'between' => self::applyBetween($qb, $dqlField, $value, $parameter),
            'empty' => $qb->andWhere("{$dqlField} IS NULL"),
            'not_empty' => $qb->andWhere("{$dqlField} IS NOT NULL"),
            default => throw new \InvalidArgumentException(sprintf('Unknown filter operator "%s" for %s.', $operator, static::class)),
        };
    }

    private static function applyBetween(QueryBuilder $qb, string $dqlField, mixed $value, string $parameter): void
    {
        if (!\is_array($value) || 2 !== \count($value)) {
            throw new \InvalidArgumentException('The "between" operator takes a two-element [from, to] array.');
        }

        [$from, $to] = array_values($value);
        $qb->andWhere("{$dqlField} BETWEEN :{$parameter}_from AND :{$parameter}_to")
            ->setParameter($parameter.'_from', $from)
            ->setParameter($parameter.'_to', $to);
    }
}
