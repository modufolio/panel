<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

use Doctrine\DBAL\Types\Types;

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
        // Every operator here names days, not instants, and the same
        // declaration is pointed at date and datetime columns alike — so each
        // bound is spelled out as the whole day it means: a lower bound is
        // midnight of its day, an upper bound is midnight of the day after,
        // exclusive. Against a date column that collapses to plain
        // comparison; against a datetime column it stops `before` and
        // `between` from ending at the stroke of midnight.
        //
        // The bounds are bound *as dates*. Left untyped, a DateTimeImmutable
        // is inferred as a datetime and formatted with a time part, which a
        // DATE column stored as text (SQLite) compares byte-wise:
        // '1995-12-15' sorts before '1995-12-15 00:00:00', so every lower
        // bound skipped its own day and every upper bound leaked one. Engines
        // that coerce the text to a date hid it.
        switch ($operator) {
            case 'on':
                $day = self::day($value);
                self::bindDay($qb, "{$dqlField} >= :{$parameter}_from", $parameter.'_from', $day);
                self::bindDay($qb, "{$dqlField} < :{$parameter}_to", $parameter.'_to', $day->modify('+1 day'));

                return;

            case 'after':
                self::bindDay($qb, "{$dqlField} >= :{$parameter}", $parameter, self::day($value));

                return;

            case 'before':
                self::bindDay($qb, "{$dqlField} < :{$parameter}", $parameter, self::day($value)->modify('+1 day'));

                return;

            case 'between':
                if (!\is_array($value) || 2 !== \count($value)) {
                    throw new \InvalidArgumentException('The "between" operator takes a two-element [from, to] array.');
                }

                [$from, $to] = array_values($value);
                self::bindDay($qb, "{$dqlField} >= :{$parameter}_from", $parameter.'_from', self::day($from));
                self::bindDay($qb, "{$dqlField} < :{$parameter}_to", $parameter.'_to', self::day($to)->modify('+1 day'));

                return;

            default:
                // empty / not_empty, and the refusal of anything undeclared.
                self::applyComparable($qb, $dqlField, $operator, $value, $parameter);
        }
    }

    /** One bound, typed as a date so the platform formats it as one. */
    private static function bindDay(\Doctrine\ORM\QueryBuilder $qb, string $predicate, string $parameter, \DateTimeImmutable $day): void
    {
        $qb->andWhere($predicate)->setParameter($parameter, $day, Types::DATE_IMMUTABLE);
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
