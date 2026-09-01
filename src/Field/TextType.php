<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A single-line text control. */
final class TextType implements FieldTypeInterface, FilterableFieldInterface
{
    use \Modufolio\Panel\Field\Concerns\FiltersText;

    public static function component(): string
    {
        return 'text';
    }

    public static function defaults(): array
    {
        return [];
    }
}
