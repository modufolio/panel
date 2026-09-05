<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/**
 * A date with a wall-clock time, edited as one control: `YYYY-MM-DDTHH:mm`.
 *
 * What a datetime column becomes when guessed — a date picker alone would
 * read the stored time as garbage and show nothing. Filtering is by day,
 * exactly as {@see DateType} does it, because "on the 15th" is the question
 * a listing asks of an appointment too.
 */
final class DateTimeType implements FieldTypeInterface, FilterableFieldInterface
{
    public static function filterOperators(): array
    {
        return DateType::filterOperators();
    }

    public static function applyFilter(\Doctrine\ORM\QueryBuilder $qb, string $dqlField, string $operator, mixed $value, string $parameter): void
    {
        DateType::applyFilter($qb, $dqlField, $operator, $value, $parameter);
    }

    public static function component(): string
    {
        return 'datetime';
    }

    public static function defaults(): array
    {
        return [];
    }
}
