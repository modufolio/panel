<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A link: a url input that must contain one. */
final class UrlType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'text';
    }

    public static function defaults(): array
    {
        return ['props' => ['type' => 'url'], 'rules' => ['url' => true]];
    }
}
