<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** One of a fixed set of options. */
final class SelectType implements FieldTypeInterface, FilterableFieldInterface
{
    public static function filterOperators(): array
    {
        return [
            'is' => 'Is',
            'is_not' => 'Is not',
            'in' => 'Is one of',
        ];
    }

    public static function applyFilter(\Doctrine\ORM\QueryBuilder $qb, string $dqlField, string $operator, mixed $value, string $parameter): void
    {
        match ($operator) {
            'is' => $qb->andWhere("{$dqlField} = :{$parameter}")->setParameter($parameter, $value),
            'is_not' => $qb->andWhere("{$dqlField} != :{$parameter}")->setParameter($parameter, $value),
            'in' => $qb->andWhere("{$dqlField} IN (:{$parameter})")->setParameter($parameter, \is_array($value) ? $value : [$value]),
            default => throw new \InvalidArgumentException(sprintf('Unknown filter operator "%s" for %s.', $operator, static::class)),
        };
    }

    public static function component(): string
    {
        return 'select';
    }

    public static function defaults(): array
    {
        return [];
    }
}
