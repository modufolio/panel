<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

use Doctrine\ORM\QueryBuilder;

/**
 * One field a user may build an ad-hoc condition against.
 *
 * Modelled on Filament's QueryBuilder constraints, and the strongest evidence
 * that a static schema is enough: a constraint declares only
 * `{key, field, type, label}` — the *operators* come from its type, so a
 * user-composable query needs no closures at all.
 *
 * Both halves are allowlisted: the field is baked in at construction, and the
 * operator must be one this type declares. A request can therefore choose
 * among conditions, but never invent one.
 */
final class Constraint
{
    public const TEXT = 'text';
    public const NUMBER = 'number';
    public const BOOLEAN = 'boolean';
    public const DATE = 'date';

    /** Operators per type, with how many values each takes. */
    private const OPERATORS = [
        self::TEXT => [
            'contains'    => ['label' => 'contains', 'values' => 1],
            'notContains' => ['label' => 'does not contain', 'values' => 1],
            'startsWith'  => ['label' => 'starts with', 'values' => 1],
            'endsWith'    => ['label' => 'ends with', 'values' => 1],
            'equals'      => ['label' => 'is', 'values' => 1],
            'notEquals'   => ['label' => 'is not', 'values' => 1],
            'isEmpty'     => ['label' => 'is empty', 'values' => 0],
            'isNotEmpty'  => ['label' => 'is not empty', 'values' => 0],
        ],
        self::NUMBER => [
            'equals'    => ['label' => 'is', 'values' => 1],
            'notEquals' => ['label' => 'is not', 'values' => 1],
            'gt'        => ['label' => 'is greater than', 'values' => 1],
            'gte'       => ['label' => 'is at least', 'values' => 1],
            'lt'        => ['label' => 'is less than', 'values' => 1],
            'lte'       => ['label' => 'is at most', 'values' => 1],
            'between'   => ['label' => 'is between', 'values' => 2],
        ],
        self::BOOLEAN => [
            'isTrue'  => ['label' => 'is yes', 'values' => 0],
            'isFalse' => ['label' => 'is no', 'values' => 0],
        ],
        self::DATE => [
            'isOn'     => ['label' => 'is on', 'values' => 1],
            'isAfter'  => ['label' => 'is after', 'values' => 1],
            'isBefore' => ['label' => 'is before', 'values' => 1],
            'between'  => ['label' => 'is between', 'values' => 2],
        ],
    ];

    private string $label;
    private ?string $icon = null;

    private function __construct(
        private readonly string $type,
        private readonly string $key,
        private readonly string $field,
    ) {
        $this->label = ucfirst(trim(preg_replace('/[_\-]+/', ' ', $key) ?? $key));
    }

    public static function text(string $key, ?string $field = null): self
    {
        return new self(self::TEXT, $key, $field ?? $key);
    }

    public static function number(string $key, ?string $field = null): self
    {
        return new self(self::NUMBER, $key, $field ?? $key);
    }

    public static function boolean(string $key, ?string $field = null): self
    {
        return new self(self::BOOLEAN, $key, $field ?? $key);
    }

    public static function date(string $key, ?string $field = null): self
    {
        return new self(self::DATE, $key, $field ?? $key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function supports(string $operator): bool
    {
        return isset(self::OPERATORS[$this->type][$operator]);
    }

    /**
     * Apply one user-chosen condition.
     *
     * Silently ignores an unknown operator or a missing value — a malformed
     * condition should narrow nothing, not error the page.
     *
     * @param array<string, mixed> $condition {operator, value, value2}
     */
    public function apply(QueryBuilder $qb, string $alias, array $condition, int $index): void
    {
        $operator = (string) ($condition['operator'] ?? '');

        if (!$this->supports($operator)) {
            return;
        }

        $arity  = self::OPERATORS[$this->type][$operator]['values'];
        $value  = $condition['value'] ?? null;
        $value2 = $condition['value2'] ?? null;

        if ($arity >= 1 && ($value === null || $value === '')) {
            return;
        }

        if ($arity === 2 && ($value2 === null || $value2 === '')) {
            return;
        }

        // $field is from the schema and $operator from the allowlist above, so
        // only these two are interpolated; values stay bound.
        $field = "{$alias}.{$this->field}";
        $p     = sprintf('c%d_%s', $index, preg_replace('/\W/', '_', $this->key));

        switch ($operator) {
            case 'contains':
                $qb->andWhere("LOWER({$field}) LIKE :{$p}")->setParameter($p, '%' . mb_strtolower((string) $value) . '%');
                break;
            case 'notContains':
                $qb->andWhere("LOWER({$field}) NOT LIKE :{$p}")->setParameter($p, '%' . mb_strtolower((string) $value) . '%');
                break;
            case 'startsWith':
                $qb->andWhere("LOWER({$field}) LIKE :{$p}")->setParameter($p, mb_strtolower((string) $value) . '%');
                break;
            case 'endsWith':
                $qb->andWhere("LOWER({$field}) LIKE :{$p}")->setParameter($p, '%' . mb_strtolower((string) $value));
                break;
            case 'equals':
                $qb->andWhere("{$field} = :{$p}")->setParameter($p, $this->cast($value));
                break;
            case 'notEquals':
                $qb->andWhere("{$field} <> :{$p}")->setParameter($p, $this->cast($value));
                break;
            case 'isEmpty':
                $qb->andWhere("({$field} IS NULL OR {$field} = '')");
                break;
            case 'isNotEmpty':
                $qb->andWhere("({$field} IS NOT NULL AND {$field} <> '')");
                break;
            case 'gt':
                $qb->andWhere("{$field} > :{$p}")->setParameter($p, $this->cast($value));
                break;
            case 'gte':
                $qb->andWhere("{$field} >= :{$p}")->setParameter($p, $this->cast($value));
                break;
            case 'lt':
                $qb->andWhere("{$field} < :{$p}")->setParameter($p, $this->cast($value));
                break;
            case 'lte':
                $qb->andWhere("{$field} <= :{$p}")->setParameter($p, $this->cast($value));
                break;
            case 'isTrue':
                $qb->andWhere("{$field} = :{$p}")->setParameter($p, true);
                break;
            case 'isFalse':
                $qb->andWhere("{$field} = :{$p}")->setParameter($p, false);
                break;
            case 'isOn':
                $day = new \DateTimeImmutable((string) $value);
                $qb->andWhere("{$field} >= :{$p}_a AND {$field} < :{$p}_b")
                    ->setParameter("{$p}_a", $day->setTime(0, 0))
                    ->setParameter("{$p}_b", $day->modify('+1 day')->setTime(0, 0));
                break;
            case 'isAfter':
                $qb->andWhere("{$field} > :{$p}")->setParameter($p, new \DateTimeImmutable((string) $value));
                break;
            case 'isBefore':
                $qb->andWhere("{$field} < :{$p}")->setParameter($p, new \DateTimeImmutable((string) $value));
                break;
            case 'between':
                if ($this->type === self::DATE) {
                    $qb->andWhere("{$field} >= :{$p}_a AND {$field} < :{$p}_b")
                        ->setParameter("{$p}_a", new \DateTimeImmutable((string) $value))
                        ->setParameter("{$p}_b", (new \DateTimeImmutable((string) $value2))->modify('+1 day'));
                } else {
                    $qb->andWhere("{$field} BETWEEN :{$p}_a AND :{$p}_b")
                        ->setParameter("{$p}_a", $this->cast($value))
                        ->setParameter("{$p}_b", $this->cast($value2));
                }
                break;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key'       => $this->key,
            'type'      => $this->type,
            'label'     => $this->label,
            'icon'      => $this->icon,
            'operators' => array_map(
                static fn(string $op, array $meta): array => [
                    'value'  => $op,
                    'label'  => $meta['label'],
                    'values' => $meta['values'],
                ],
                array_keys(self::OPERATORS[$this->type]),
                self::OPERATORS[$this->type],
            ),
        ], static fn(mixed $value): bool => $value !== null);
    }

    private function cast(mixed $value): mixed
    {
        return $this->type === self::NUMBER ? (float) $value : $value;
    }
}
