<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A whole number. */
final class NumberType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'text';
    }

    public static function defaults(): array
    {
        return ['props' => ['type' => 'number'], 'rules' => ['integer' => true]];
    }
}
