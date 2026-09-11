<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

/**
 * "Red below zero, green above a thousand" — a value's colour, decided by the
 * value.
 *
 * Declarative like everything else on a {@see Column}: an operator, a bound and
 * a colour, all of which survive json_encode and cross the Inertia prop
 * boundary. The comparison itself happens on the client, and that is the point
 * rather than an implementation detail:
 *
 *  - a row that arrives over the realtime channel is coloured by the same rule
 *    the page was rendered with, without asking the server what colour it is
 *    now;
 *  - an in-place edit recolours the cell as the number changes, before it is
 *    saved;
 *  - the rule travels with the schema, so a filter chip or an export could read
 *    the same thresholds later.
 *
 * A value computed *per row* on the server — an overdue flag, a health score —
 * is a different thing, and belongs in the presenter as its own field.
 *
 * Operator keys are the shared vocabulary: equals, not_equals, gt, gte, lt,
 * lte, between, empty, not_empty (see FiltersComparable). One set of names
 * across filters, constraints and now colours.
 */
final class ColorRule
{
    private const OPERATORS = [
        'equals', 'not_equals', 'gt', 'gte', 'lt', 'lte', 'between', 'empty', 'not_empty',
    ];

    private ?string $icon = null;

    private function __construct(
        private readonly string $operator,
        private readonly mixed $value,
        private readonly string $color,
    ) {
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown colour rule operator "%s" — expected one of: %s.',
                $operator,
                implode(', ', self::OPERATORS),
            ));
        }

        if ($operator === 'between' && (!is_array($value) || count($value) !== 2)) {
            throw new \InvalidArgumentException('The "between" operator takes a two-element [from, to] array.');
        }
    }

    /** The general form, for an operator without a named constructor. */
    public static function when(string $operator, mixed $value, string $color): self
    {
        return new self($operator, $value, $color);
    }

    /** Strictly below the bound. */
    public static function below(int|float $bound, string $color): self
    {
        return new self('lt', $bound, $color);
    }

    /** The bound included. */
    public static function atMost(int|float $bound, string $color): self
    {
        return new self('lte', $bound, $color);
    }

    /** Strictly above the bound. */
    public static function above(int|float $bound, string $color): self
    {
        return new self('gt', $bound, $color);
    }

    /** The bound included. */
    public static function atLeast(int|float $bound, string $color): self
    {
        return new self('gte', $bound, $color);
    }

    /** Both bounds included, as everywhere else `between` is used here. */
    public static function between(int|float $from, int|float $to, string $color): self
    {
        return new self('between', [$from, $to], $color);
    }

    public static function equals(mixed $value, string $color): self
    {
        return new self('equals', $value, $color);
    }

    /** Null, or an empty string — the placeholder case, when it deserves colour. */
    public static function empty(string $color): self
    {
        return new self('empty', null, $color);
    }

    /** An icon before the value, for a rule that should read without colour too. */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @return array{operator: string, value?: mixed, color: string, icon?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'operator' => $this->operator,
            'value'    => $this->value,
            'color'    => $this->color,
            'icon'     => $this->icon,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
