<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** Multi-line prose. Rows can be overridden per field. */
final class TextareaType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'textarea';
    }

    public static function defaults(): array
    {
        return ['props' => ['rows' => 3]];
    }
}
