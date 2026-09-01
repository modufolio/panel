<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field;

/**
 * Read-only raw data, shown but never edited. The importer's parking spot:
 * source fields that did not map onto the model stay visible here instead of
 * silently disappearing, so nothing from the old system is lost invisibly.
 */
final class DataType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'data';
    }

    public static function defaults(): array
    {
        return [
            'props' => ['readonly' => true],
        ];
    }
}
