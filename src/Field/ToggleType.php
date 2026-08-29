<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** An on/off switch. */
final class ToggleType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'toggle';
    }

    public static function defaults(): array
    {
        return [];
    }
}
