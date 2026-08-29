<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A comma-separated list, edited as chips. */
final class TagsType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'tags';
    }

    public static function defaults(): array
    {
        return ['help' => 'Press Enter or comma to add.'];
    }
}
