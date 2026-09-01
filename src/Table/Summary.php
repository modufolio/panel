<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

/**
 * An aggregate shown in a column's footer.
 *
 * The *type* names the SQL aggregate, so nothing here is a closure — the
 * value is computed server-side over the filtered set (not just the current
 * page, which is the whole point of a summary).
 */
final class Summary
{
    public const SUM = 'sum';
    public const AVERAGE = 'avg';
    public const COUNT = 'count';
    public const MIN = 'min';
    public const MAX = 'max';

    private readonly string $label;

    /** @param self::SUM|self::AVERAGE|self::COUNT|self::MIN|self::MAX $type */
    private function __construct(
        private readonly string $type,
        ?string $label = null,
    ) {
        $this->label = $label ?? ucfirst($type);
    }

    public static function sum(?string $label = 'Total'): self
    {
        return new self(self::SUM, $label);
    }

    public static function average(?string $label = 'Avg'): self
    {
        return new self(self::AVERAGE, $label);
    }

    public static function count(?string $label = 'Count'): self
    {
        return new self(self::COUNT, $label);
    }

    public static function min(?string $label = 'Min'): self
    {
        return new self(self::MIN, $label);
    }

    public static function max(?string $label = 'Max'): self
    {
        return new self(self::MAX, $label);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return $this->label;
    }

    /** DQL aggregate expression over the given field. */
    public function expression(string $field): string
    {
        return match ($this->type) {
            self::SUM     => "SUM({$field})",
            self::AVERAGE => "AVG({$field})",
            self::COUNT   => "COUNT({$field})",
            self::MIN     => "MIN({$field})",
            self::MAX     => "MAX({$field})",
        };
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'label' => $this->label];
    }
}
