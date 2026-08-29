<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** An address: an email input that must contain one. */
final class EmailType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'text';
    }

    public static function defaults(): array
    {
        return ['props' => ['type' => 'email'], 'rules' => ['email' => true]];
    }
}
