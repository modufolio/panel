<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field;

/**
 * A server-computed, read-only value — no storage, no input. The `accessor`
 * option names the method on the record (or presenter) whose return value
 * the field displays; requiring it up front is the lesson Keystone's
 * virtual field enforces with its ui.query throw: never guess how to
 * display a computed value.
 *
 *     $fields->add('word_count', ComputedType::class, [
 *         'accessor' => 'wordCount',
 *     ]);
 */
final class ComputedType implements FieldTypeInterface, RequiresOptionsInterface
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

    public static function requiredOptions(): array
    {
        return ['accessor'];
    }
}
