<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Field\DateType;
use Modufolio\Panel\Field\FilterableFieldInterface;
use Modufolio\Panel\Field\NumberType;
use Modufolio\Panel\Field\TextType;
use Modufolio\Panel\Field\ToggleType;

/**
 * One field a user may build an ad-hoc condition against.
 *
 * Modelled on Filament's QueryBuilder constraints, and the strongest evidence
 * that a static schema is enough: a constraint declares only
 * `{key, field, type, label}` — the *operators* come from its type, so a
 * user-composable query needs no closures at all.
 *
 * "Its type" is now the field type itself: each of the four kinds names a
 * {@see FilterableFieldInterface} implementation, and both the operator menu
 * and the predicate come from there. This class used to carry a second
 * operator table of its own — same concepts under different names
 * (`notContains` beside `not_contains`, `isEmpty` beside `empty`), two
 * switches to keep in step, and a listing filter that could never agree with
 * the JSON:API layer's vocabulary. What remains here is what a *constraint*
 * knows and a field type cannot: which entity field it points at, how many
 * values each operator takes, and how a request's strings become bound values.
 *
 * Both halves are still allowlisted: the field is baked in at construction,
 * and the operator must be one the type declares. A request can therefore
 * choose among conditions, but never invent one.
 */
final class Constraint
{
    public const TEXT = 'text';
    public const NUMBER = 'number';
    public const BOOLEAN = 'boolean';
    public const DATE = 'date';

    /**
     * The field type behind each kind — the source of both the operator menu
     * and the predicate.
     *
     * @var array<string, class-string<FilterableFieldInterface>>
     */
    private const TYPES = [
        self::TEXT => TextType::class,
        self::NUMBER => NumberType::class,
        self::BOOLEAN => ToggleType::class,
        self::DATE => DateType::class,
    ];

    /**
     * How many values an operator takes.
     *
     * Arity is a property of the shared vocabulary, not of any one type: every
     * type that declares `between` takes two bounds, and `empty` never takes a
     * value. Anything not named here takes one, which is why the map lists
     * only the exceptions.
     */
    private const ARITY = [
        'between' => 2,
        'empty' => 0,
        'not_empty' => 0,
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
        return isset($this->operators()[$operator]);
    }

    /**
     * Apply one user-chosen condition.
     *
     * Silently ignores an unknown operator or a missing value — a malformed
     * condition should narrow nothing, not error the page. The field type's
     * own `applyFilter()` throws on an undeclared operator, which is the right
     * answer for a caller bug; here the caller is the query string, so the
     * allowlist runs first and nothing undeclared ever reaches it.
     *
     * @param array<string, mixed> $condition {operator, value, value2}
     */
    public function apply(QueryBuilder $qb, string $alias, array $condition, int $index): void
    {
        $operator = (string) ($condition['operator'] ?? '');

        if (!$this->supports($operator)) {
            return;
        }

        $arity  = $this->arity($operator);
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

        $bound = match ($arity) {
            0 => null,
            2 => [$this->cast($value), $this->cast($value2)],
            default => $this->cast($value),
        };

        $type = self::TYPES[$this->type];

        $type::applyFilter($qb, $field, $operator, $bound, $p);
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
                fn(string $op, string $label): array => [
                    'value'  => $op,
                    'label'  => $label,
                    'values' => $this->arity($op),
                ],
                array_keys($this->operators()),
                array_values($this->operators()),
            ),
        ], static fn(mixed $value): bool => $value !== null);
    }

    /** @return array<string, string> operator key => label, in menu order */
    private function operators(): array
    {
        $type = self::TYPES[$this->type];

        return $type::filterOperators();
    }

    private function arity(string $operator): int
    {
        return self::ARITY[$operator] ?? 1;
    }

    /**
     * A request carries strings; the predicates compare against typed columns.
     *
     * Left here rather than pushed into the field types: a type filters
     * whatever value it is handed, and knowing that this particular value
     * arrived as text in a query string is the constraint's business.
     */
    private function cast(mixed $value): mixed
    {
        return match ($this->type) {
            self::NUMBER => (float) $value,
            self::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOL),
            self::DATE => new \DateTimeImmutable((string) $value),
            default => $value,
        };
    }
}
