<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** Rich body content, edited as a ProseMirror document. */
final class BuilderType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'builder';
    }

    public static function defaults(): array
    {
        return [];
    }
}
