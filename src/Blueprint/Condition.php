<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

/**
 * Server-side evaluator for a field's `when` / `requiredWhen` condition —
 * the same shape the client evaluates (see ui/src/Components/Fields/
 * useBlueprint.ts): a tuple, or `all` / `any` / `not` combinators.
 *
 *     ['status', 'published']              // implicit ==
 *     ['status', '!=', 'draft']
 *     ['cover', 'not_empty']
 *     ['all' => [ ... ]]  ['any' => [ ... ]]  ['not' => ...]
 *
 * The client copy saves a round trip; this is the one that decides: a value
 * submitted for a hidden field must not smuggle past validation merely
 * because the browser did not render the input.
 */
final class Condition
{
    /**
     * @param array<mixed>         $condition
     * @param array<string, mixed> $values
     */
    public static function evaluate(array $condition, array $values): bool
    {
        if (isset($condition['all']) && \is_array($condition['all'])) {
            foreach ($condition['all'] as $inner) {
                if (!\is_array($inner) || !self::evaluate($inner, $values)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($condition['any']) && \is_array($condition['any'])) {
            foreach ($condition['any'] as $inner) {
                if (\is_array($inner) && self::evaluate($inner, $values)) {
                    return true;
                }
            }

            return false;
        }

        if (\array_key_exists('not', $condition)) {
            return \is_array($condition['not']) && !self::evaluate($condition['not'], $values);
        }

        return self::evaluateTuple($condition, $values);
    }

    /**
     * @param array<mixed>         $tuple
     * @param array<string, mixed> $values
     */
    private static function evaluateTuple(array $tuple, array $values): bool
    {
        $key = $tuple[0] ?? null;
        if (!\is_string($key)) {
            return true;
        }

        $actual = $values[$key] ?? null;
        $second = $tuple[1] ?? null;
        $hasThird = \array_key_exists(2, $tuple);

        // Two-element forms: unary operator, or implicit equality.
        if (!$hasThird) {
            return match ($second) {
                'empty' => self::isEmpty($actual),
                'not_empty' => !self::isEmpty($actual),
                default => $actual === $second,
            };
        }

        $expected = $tuple[2];

        return match ($second) {
            '==' => $actual === $expected,
            '!=' => $actual !== $expected,
            '>' => (float) self::numeric($actual) > (float) self::numeric($expected),
            '>=' => (float) self::numeric($actual) >= (float) self::numeric($expected),
            '<' => (float) self::numeric($actual) < (float) self::numeric($expected),
            '<=' => (float) self::numeric($actual) <= (float) self::numeric($expected),
            'in' => \is_array($expected) && \in_array($actual, $expected, true),
            'not_in' => \is_array($expected) && !\in_array($actual, $expected, true),
            'contains' => \is_array($actual)
                ? \in_array($expected, $actual, true)
                : str_contains((string) self::stringable($actual), (string) self::stringable($expected)),
            default => true,
        };
    }

    private static function isEmpty(mixed $value): bool
    {
        return null === $value || '' === $value || [] === $value;
    }

    private static function numeric(mixed $value): float|int|string
    {
        return \is_int($value) || \is_float($value) || \is_string($value) ? $value : 0;
    }

    private static function stringable(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
