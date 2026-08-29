<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** One of a fixed set of options. */
final class SelectType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'select';
    }

    public static function defaults(): array
    {
        return [];
    }
}
