<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/**
 * A break between fields — not a value, not stored, never submitted.
 *
 * Added through {@see \Modufolio\Panel\Blueprint\BlueprintBuilder::separator()}
 * rather than `add()`, which is what gives it a key nobody has to invent. The
 * write path skips the type outright, so a separator can never reach a setter.
 */
final class SeparatorType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'separator';
    }

    public static function defaults(): array
    {
        return ['width' => 'full'];
    }
}
