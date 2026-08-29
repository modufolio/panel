<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A single-line text control. */
final class TextType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'text';
    }

    public static function defaults(): array
    {
        return [];
    }
}
