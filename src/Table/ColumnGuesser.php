<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Table;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Modufolio\Panel\Blueprint\EnumOptions;
use Modufolio\Panel\Contracts\HasColorInterface;

/**
 * What the mapping already knows about a column, filled in.
 *
 * The form has read the entity's mapping since the beginning — a date column
 * is a date field, an `enumType` column is a select over its cases. The table
 * did not, so the same enum had to be spelled out again per listing
 * (`->type('badge')->options(Status::class)->colors(Status::class)`) or read
 * as the raw stored value: `on_hold`, in the grey of a text cell, beside a
 * timestamp printed as `2026-09-08T07:26:29+00:00`.
 *
 * Two guesses, both last in line — anything the column declares wins:
 *
 *  - a date or datetime column renders as a date;
 *  - an `enumType` column carries its cases as options, so the cell shows the
 *    case's label rather than its stored value, and becomes a badge when the
 *    enum knows its own colours ({@see HasColorInterface}).
 *
 * A column reading a path (`studio.name`), a presenter-only key or a key the
 * entity does not map is left alone: there is nothing to read it from.
 */
final class ColumnGuesser
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @param class-string $entityClass */
    public function apply(TableSchema $schema, string $entityClass): void
    {
        try {
            $meta = $this->entityManager->getClassMetadata($entityClass);
        } catch (\Throwable) {
            // Nothing mapped to read from; the declaration stands as written.
            return;
        }

        foreach ($schema->declaredColumns() as $column) {
            $this->fill($column, $meta);
        }
    }

    /** @param ClassMetadata<object> $meta */
    private function fill(Column $column, ClassMetadata $meta): void
    {
        $field = self::property($column->field());

        if (str_contains($field, '.') || !$meta->hasField($field)) {
            return;
        }

        $mapping = $meta->getFieldMapping($field);
        $enum    = $mapping['enumType'] ?? null;

        if (is_string($enum) && is_a($enum, \BackedEnum::class, true)) {
            $this->fillEnum($column, $enum);

            return;
        }

        if (!$column->hasDeclaredType() && in_array((string) $meta->getTypeOfField($field), [
            'date', 'date_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable',
        ], true)) {
            $column->type('date');
        }
    }

    /** @param class-string<\BackedEnum> $enum */
    private function fillEnum(Column $column, string $enum): void
    {
        if (!$column->hasOptions()) {
            // The cases as `{value, label}`: the same list the form's select
            // gets, so a cell and a field name a case the same way.
            $column->options(EnumOptions::for($enum));
        }

        if (!is_a($enum, HasColorInterface::class, true)) {
            return;
        }

        if (!$column->hasColors()) {
            $column->colors($enum);
        }

        if (!$column->hasDeclaredType()) {
            $column->type('badge');
        }
    }

    /** `released_on` → `releasedOn`, as the derived query resolves it. */
    private static function property(string $path): string
    {
        return implode('.', array_map(
            static fn (string $segment): string => lcfirst(str_replace('_', '', ucwords($segment, '_'))),
            explode('.', $path),
        ));
    }
}
