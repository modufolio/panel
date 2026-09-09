<?php

declare(strict_types=1);

namespace Modufolio\Panel\Metric;

use Modufolio\Panel\Contracts\HasColorInterface;
use Modufolio\Panel\Support\Label;
use Modufolio\Panel\Table\Summary;

/**
 * A number about a resource, shown above its listing.
 *
 * Three shapes, which is what the ecosystems this borrows from settled on:
 * one number (optionally against the period before it), a series over time,
 * and a breakdown by a field.
 *
 *     Metric::value('movies')->count()->icon('film'),
 *     Metric::value('average_rating')->average('rating')->decimals(1),
 *     Metric::trend('added')->count()->over('createdAt')->days(30),
 *     Metric::partition('genre')->count()->colors(Genre::class),
 *
 * Declarative only, for the same reason {@see \Modufolio\Panel\Table\Column}
 * is: this crosses an Inertia prop boundary, so nothing may be a closure. The
 * *aggregate* names a SQL function and the *field* names a column; what to
 * compute is therefore data, and {@see MetricCalculator} does the computing.
 *
 * Factories take the key alone. The label is humanised from it until
 * {@see label()} says otherwise, and a partition groups by the key until
 * {@see by()} does — the same convention columns and drawer tabs follow.
 */
final class Metric
{
    public const VALUE = 'value';
    public const TREND = 'trend';
    public const PARTITION = 'partition';

    private string $label;
    private Summary $aggregate;
    private ?string $field = null;
    private ?string $dateField = null;
    private ?int $days = null;
    private ?int $months = null;
    private bool $compare = false;
    private ?string $groupField = null;
    private ?string $icon = null;
    private ?string $currency = null;
    private ?int $decimals = null;
    private ?int $limit = null;

    /**
     * Value → colour. Keyed `array-key` rather than `string` because PHP turns
     * a numeric-looking key into an int whatever the declaration says, and a
     * partition over a year or a rating is exactly that case.
     *
     * @var array<array-key, string>|null
     */
    private ?array $colors = null;

    private function __construct(
        private readonly string $key,
        private readonly string $type,
    ) {
        $this->label     = Label::sentence($key);
        $this->aggregate = Summary::count();
    }

    /** One number: how many, how much, how high. */
    public static function value(string $key): self
    {
        return new self($key, self::VALUE);
    }

    /**
     * A series over time. Needs the date field it walks — {@see over()} — and
     * a window, {@see days()} or {@see months()}.
     */
    public static function trend(string $key): self
    {
        return new self($key, self::TREND);
    }

    /** A breakdown by a field, grouped server-side. Groups by the key unless {@see by()} says otherwise. */
    public static function partition(string $key): self
    {
        return new self($key, self::PARTITION);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** How many rows. The default, so a plain count needs no aggregate at all. */
    public function count(): self
    {
        $this->aggregate = Summary::count();
        $this->field     = null;

        return $this;
    }

    public function sum(string $field): self
    {
        $this->aggregate = Summary::sum();
        $this->field     = $field;

        return $this;
    }

    public function average(string $field): self
    {
        $this->aggregate = Summary::average();
        $this->field     = $field;

        return $this;
    }

    public function min(string $field): self
    {
        $this->aggregate = Summary::min();
        $this->field     = $field;

        return $this;
    }

    public function max(string $field): self
    {
        $this->aggregate = Summary::max();
        $this->field     = $field;

        return $this;
    }

    /**
     * The date field a window is measured on: `createdAt`, `startsOn`.
     *
     * Required by a trend, and what lets a value metric be about a period
     * rather than about everything.
     */
    public function over(string $dateField): self
    {
        $this->dateField = $dateField;

        return $this;
    }

    /** A window of N days, bucketed by day for a trend. */
    public function days(int $days): self
    {
        $this->days   = max(1, $days);
        $this->months = null;

        return $this;
    }

    /** A window of N months, bucketed by month for a trend. */
    public function months(int $months): self
    {
        $this->months = max(1, $months);
        $this->days   = null;

        return $this;
    }

    /**
     * Also compute the window before this one, so the card can say which way
     * the number is going. Meaningless without a window, and refused there
     * rather than silently reported as no change.
     */
    public function compare(bool $compare = true): self
    {
        $this->compare = $compare;

        return $this;
    }

    /** The field a partition groups by, when it is not the metric's key. */
    public function by(string $field): self
    {
        $this->groupField = $field;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** Render the number as currency. Values are assumed to be in major units. */
    public function money(string $currency = 'EUR'): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function decimals(int $decimals): self
    {
        $this->decimals = $decimals;

        return $this;
    }

    /** Keep the N largest slices of a partition; the rest are summed as "Other". */
    public function limit(int $slices): self
    {
        $this->limit = max(1, $slices);

        return $this;
    }

    /**
     * Value → colour map for a partition's slices.
     *
     * Accepts a literal map or a backed enum implementing
     * {@see HasColorInterface} — `->colors(Genre::class)` — exactly as
     * {@see \Modufolio\Panel\Table\Column::colors()} does, so a status keeps
     * one set of colours across the table and the chart.
     *
     * @param array<array-key, string>|class-string $colors
     */
    public function colors(array|string $colors): self
    {
        $this->colors = is_string($colors) ? self::colorsFromEnum($colors) : $colors;

        return $this;
    }

    // ── What the calculator asks ─────────────────────────────────────────────

    public function key(): string
    {
        return $this->key;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function aggregate(): Summary
    {
        return $this->aggregate;
    }

    /** The field aggregated over, or null for a count. */
    public function field(): ?string
    {
        return $this->field;
    }

    public function dateField(): ?string
    {
        return $this->dateField;
    }

    /** The field a partition groups by. */
    public function groupField(): string
    {
        return $this->groupField ?? $this->key;
    }

    public function wantsComparison(): bool
    {
        return $this->compare;
    }

    public function slices(): ?int
    {
        return $this->limit;
    }

    /** @return array<array-key, string>|null */
    public function colorMap(): ?array
    {
        return $this->colors;
    }

    /** How many buckets a trend has, or how long a value's window is. */
    public function windowLength(): ?int
    {
        return $this->days ?? $this->months;
    }

    /** 'day' or 'month', or null when the metric names no window. */
    public function bucket(): ?string
    {
        if ($this->days !== null) {
            return 'day';
        }

        return $this->months !== null ? 'month' : null;
    }

    /**
     * Fail on a declaration that cannot be computed, rather than on the SQL it
     * would have produced.
     */
    public function validate(): void
    {
        if ($this->type === self::TREND && ($this->dateField === null || $this->bucket() === null)) {
            throw new \LogicException(sprintf(
                'Metric "%s" is a trend, so it needs the date field it walks and a window: '
                . '->over(\'createdAt\')->days(30).',
                $this->key,
            ));
        }

        if ($this->compare && ($this->dateField === null || $this->bucket() === null)) {
            throw new \LogicException(sprintf(
                'Metric "%s" compares against the previous period, so it needs a window to compare: '
                . '->over(\'createdAt\')->days(30)->compare().',
                $this->key,
            ));
        }

        if ($this->aggregate->type() !== Summary::COUNT && trim((string) $this->field) === '') {
            throw new \LogicException(sprintf(
                'Metric "%s" aggregates no field: sum(), average(), min() and max() each name the field they read.',
                $this->key,
            ));
        }
    }

    /**
     * The declaration the client renders from. The numbers are added by
     * {@see MetricCalculator}, so this is everything that is true before the
     * database is asked.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key'      => $this->key,
            'type'     => $this->type,
            'label'    => $this->label,
            'icon'     => $this->icon,
            'currency' => $this->currency,
            'decimals' => $this->decimals,
            'bucket'   => $this->bucket(),
            'window'   => $this->windowLength(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  class-string             $enum
     * @return array<array-key, string>
     */
    private static function colorsFromEnum(string $enum): array
    {
        if (!is_a($enum, \BackedEnum::class, true) || !is_a($enum, HasColorInterface::class, true)) {
            throw new \LogicException(sprintf(
                'Metric::colors() was given "%s", which is not a backed enum implementing %s.',
                $enum,
                HasColorInterface::class,
            ));
        }

        $colors = [];

        foreach ($enum::cases() as $case) {
            $colors[(string) $case->value] = $case->getColor();
        }

        return $colors;
    }
}
