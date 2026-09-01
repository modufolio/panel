<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A calendar date, stored as YYYY-MM-DD. */
final class DateType implements FieldTypeInterface, FilterableFieldInterface
{
    use \Modufolio\Panel\Field\Concerns\FiltersComparable {
        filterOperators as private comparableOperators;
        applyFilter as private applyComparable;
    }

    /**
     * Date filtering speaks modufolio/json-api's DateFilter vocabulary —
     * on/after/before — mapped onto the shared comparable predicates.
     */
    public static function filterOperators(): array
    {
        return [
            'on' => 'On',
            'after' => 'After',
            'before' => 'Before',
            'between' => 'Between',
            'empty' => 'Is empty',
            'not_empty' => 'Is not empty',
        ];
    }

    public static function applyFilter(\Doctrine\ORM\QueryBuilder $qb, string $dqlField, string $operator, mixed $value, string $parameter): void
    {
        // `on` is a day, not an instant. Against a date column `= :day` would
        // do, but the same declaration is what a listing points at a datetime
        // column, where equality matches only the stroke of midnight — so the
        // one operator whose meaning depends on the column's precision spells
        // out the half-open day it means.
        if ($operator === 'on') {
            $day = self::day($value);

            $qb->andWhere("{$dqlField} >= :{$parameter}_from AND {$dqlField} < :{$parameter}_to")
                ->setParameter($parameter.'_from', $day)
                ->setParameter($parameter.'_to', $day->modify('+1 day'));

            return;
        }

        self::applyComparable($qb, $dqlField, match ($operator) {
            'after' => 'gte',
            'before' => 'lte',
            default => $operator,
        }, $value, $parameter);
    }

    /** Midnight of the day $value names, whether it arrived as text or a date. */
    private static function day(mixed $value): \DateTimeImmutable
    {
        $date = $value instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($value)
            : new \DateTimeImmutable((string) $value);

        return $date->setTime(0, 0);
    }

    public static function component(): string
    {
        return 'date';
    }

    public static function defaults(): array
    {
        return [];
    }
}
