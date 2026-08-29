<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/**
 * A fractional number — quantities, weights, prices.
 *
 * Split from {@see NumberType} rather than parameterised on it because the
 * two differ in what they refuse: NumberType's `integer` rule must reject
 * "2.5" for a year column, while a recipe line's 2.5 g is exactly the point.
 * The step granularity comes from the column's scale, set by the guesser.
 */
final class DecimalType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'text';
    }

    public static function defaults(): array
    {
        return ['props' => ['type' => 'number']];
    }
}
