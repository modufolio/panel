<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A colour, stored as a hex literal. */
final class ColorType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'color';
    }

    public static function defaults(): array
    {
        return [];
    }
}
