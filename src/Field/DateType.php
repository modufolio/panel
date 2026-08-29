<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A calendar date, stored as YYYY-MM-DD. */
final class DateType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'date';
    }

    public static function defaults(): array
    {
        return [];
    }
}
