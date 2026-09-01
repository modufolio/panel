<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** An on/off switch. */
final class ToggleType implements FieldTypeInterface, FilterableFieldInterface
{
    public static function filterOperators(): array
    {
        return ['is' => 'Is'];
    }

    public static function applyFilter(\Doctrine\ORM\QueryBuilder $qb, string $dqlField, string $operator, mixed $value, string $parameter): void
    {
        match ($operator) {
            'is' => $qb->andWhere("{$dqlField} = :{$parameter}")->setParameter($parameter, (bool) $value),
            default => throw new \InvalidArgumentException(sprintf('Unknown filter operator "%s" for %s.', $operator, static::class)),
        };
    }

    public static function component(): string
    {
        return 'toggle';
    }

    public static function defaults(): array
    {
        return [];
    }
}
