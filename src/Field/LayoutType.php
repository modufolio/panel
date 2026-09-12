<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/** A page builder: rows of columns, each column a stack of typed blocks. */
final class LayoutType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'layout';
    }

    public static function defaults(): array
    {
        return [
            'width' => 'full',
            'props' => [
                'layouts' => ['1/1', '1/2 1/2', '1/3 1/3 1/3', '2/3 1/3', '1/3 2/3', '1/4 1/4 1/4 1/4'],
            ],
        ];
    }
}
