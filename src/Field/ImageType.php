<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A media-library reference, stored as a uuid. */
final class ImageType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'image';
    }

    public static function defaults(): array
    {
        return ['help' => 'Pick from the media library. The reference is stored, not the URL.'];
    }
}
