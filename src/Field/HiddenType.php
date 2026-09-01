<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field;

/**
 * Stored but never shown in the editor — importer bookkeeping, feature
 * flags a later migration flips, values other fields compute.
 */
final class HiddenType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'hidden';
    }

    public static function defaults(): array
    {
        return [];
    }
}
