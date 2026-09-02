<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Table;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;

/**
 * A declarative table filter.
 *
 * Like {@see Column}, everything here is JSON-serialisable — the filter
 * *control* is rendered from this description, and the *predicate* is applied
 * server-side by {@see apply()}. Filament reaches for a
 * `query(Builder $q, array $data)` closure for anything beyond a plain select;
 * we keep the predicate declarative by pairing a fixed filter type with a
 * hardcoded entity field.
 *
 * The field is never taken from the request — it is baked in at construction,
 * which is what makes interpolating it into DQL safe.
 */
final class Filter
{
    public const SELECT = 'select';
    public const MULTI_SELECT = 'multiSelect';
    public const TERNARY = 'ternary';
    public const TRASHED = 'trashed';
    public const DATE_RANGE = 'dateRange';

    private string $label;
    private ?string $placeholder = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $options = null;

    /**
     * True when the resolved options are a bounded slice of a larger set. The
     * panel's rule is that a bound it imposes is visible, so this travels to
     * the client rather than the list silently ending early.
     */
    private bool $optionsTruncated = false;

    private ?RelationOptions $relation = null;

    private bool $handledByQuery = false;

    private string $trueLabel = 'Yes';
    private string $falseLabel = 'No';
    private string $trueValue = '1';
    private string $falseValue = '0';

    private function __construct(
        private readonly string $type,
        private readonly string $key,
        private ?string $field = null,
    ) {
        $this->label = ucfirst(trim(preg_replace('/[_\-]+/', ' ', $key) ?? $key));
        $this->field ??= $key;
    }

    public static function select(string $key, ?string $field = null): self
    {
        return new self(self::SELECT, $key, $field);
    }

    public static function multiSelect(string $key, ?string $field = null): self
    {
        return new self(self::MULTI_SELECT, $key, $field);
    }

    public static function ternary(string $key, ?string $field = null): self
    {
        return new self(self::TERNARY, $key, $field);
    }

    public static function dateRange(string $key, ?string $field = null): self
    {
        return new self(self::DATE_RANGE, $key, $field);
    }

    /**
     * Soft-delete scope.
     *
     * Declares the control only — the predicate belongs to the resource's list
     * query (FilterTrashedQuery), which already owns `trashed`. Two places
     * applying it would double-filter.
     */
    public static function trashed(): self
    {
        return (new self(self::TRASHED, 'trashed', 'deletedAt'))
            ->handledByQuery()
            ->label('Deleted')
            ->trueOption('With Deleted', 'with')
            ->falseOption('Only Deleted', 'only');
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Choices for a select filter — a literal list or a backed enum class.
     *
     * @param list<array<string, mixed>>|class-string $options
     */
    public function options(array|string $options): self
    {
        if (is_string($options)) {
            if (!method_exists($options, 'toOptions')) {
                throw new \LogicException(sprintf(
                    'Filter::options() was given "%s", which does not provide toOptions().',
                    $options,
                ));
            }

            $options = $options::toOptions();
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Populate the choices from a related entity.
     *
     * Resolved to a flat option list by ResourceListing at serialisation time,
     * so the client still receives plain data.
     *
     * @param class-string $entityClass
     */
    public function relationship(string $entityClass, string $labelField, ?string $valueField = null): self
    {
        $this->relation = new RelationOptions($entityClass, $labelField, $valueField ?? 'id');

        return $this;
    }

    /**
     * Render the control, but leave the predicate to the resource's list query.
     *
     * For filters the query already owns — `trashed`, or User's `role`, which
     * matches against a JSON `roles` array rather than a scalar column.
     * Applying it in both places would double-filter.
     */
    public function handledByQuery(bool $handledByQuery = true): self
    {
        $this->handledByQuery = $handledByQuery;

        return $this;
    }

    public function trueOption(string $label, string $value = '1'): self
    {
        $this->trueLabel = $label;
        $this->trueValue = $value;

        return $this;
    }

    public function falseOption(string $label, string $value = '0'): self
    {
        $this->falseLabel = $label;
        $this->falseValue = $value;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function relation(): ?RelationOptions
    {
        return $this->relation;
    }

    /**
     * @param list<array<string, mixed>> $options
     */
    public function withResolvedOptions(array $options, bool $truncated = false): self
    {
        $clone = clone $this;
        $clone->options = $options;
        $clone->optionsTruncated = $truncated;
        $clone->relation = null;

        return $clone;
    }

    /**
     * Narrow $qb by this filter's value.
     *
     * A no-op for an empty value or for `trashed`, whose predicate lives in
     * the list query.
     */
    public function apply(QueryBuilder $qb, string $alias, mixed $value): void
    {
        if ($this->type === self::TRASHED || $this->handledByQuery || $this->isEmpty($value)) {
            return;
        }

        // $field is hardcoded in PHP, never read from the request — that is
        // what makes interpolating it here safe. Values stay bound.
        $field = "{$alias}.{$this->field}";
        $param = 'f_' . preg_replace('/\W/', '_', $this->key);

        switch ($this->type) {
            case self::SELECT:
                if ($this->matchesRelationField()) {
                    // The options carry the related entity's $valueField (e.g.
                    // Organization uuids), but $field is the association — a
                    // direct comparison would match the FK against a uuid and
                    // find nothing. Resolve through a subquery instead of a
                    // join so the same predicate works on the count builder.
                    // entityClass and valueField are hardcoded in PHP, like
                    // $field above — never read from the request.
                    $qb->andWhere("{$field} IN ({$this->relationSubquery($param)})")
                        ->setParameter($param, $value);
                    break;
                }

                $qb->andWhere("{$field} = :{$param}")->setParameter($param, $value);
                break;

            case self::MULTI_SELECT:
                $values = is_array($value) ? $value : explode(',', (string)$value);
                $values = array_values(array_filter(array_map('trim', $values), static fn ($v) => $v !== ''));

                if ($values === []) {
                    break;
                }

                if ($this->matchesRelationField()) {
                    $qb->andWhere("{$field} IN ({$this->relationSubquery($param, in: true)})")
                        ->setParameter($param, $values);
                    break;
                }

                $qb->andWhere("{$field} IN (:{$param})")->setParameter($param, $values);
                break;

            case self::TERNARY:
                $qb->andWhere("{$field} = :{$param}")
                    ->setParameter($param, (string)$value === $this->trueValue);
                break;

            case self::DATE_RANGE:
                $from  = is_array($value) ? ($value['from'] ?? $value['start'] ?? null) : null;
                $until = is_array($value) ? ($value['until'] ?? $value['end'] ?? null) : null;

                // Bound as dates, not datetimes: a DATE column stored as text
                // (SQLite) compares byte-wise, and '1995-12-15' sorts before
                // '1995-12-15 00:00:00' — see DateType::applyFilter().
                if ($from !== null && $from !== '') {
                    $qb->andWhere("{$field} >= :{$param}_from")
                        ->setParameter("{$param}_from", new \DateTimeImmutable((string)$from), Types::DATE_IMMUTABLE);
                }

                if ($until !== null && $until !== '') {
                    // Inclusive of the whole end day.
                    $qb->andWhere("{$field} < :{$param}_until")
                        ->setParameter(
                            "{$param}_until",
                            (new \DateTimeImmutable((string)$until))->modify('+1 day'),
                            Types::DATE_IMMUTABLE,
                        );
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
            'key'         => $this->key,
            'type'        => $this->type,
            'label'       => $this->label,
            'placeholder' => $this->placeholder,
            'options'     => $this->options,
            // Only emitted when true: array_filter below drops nulls, and a
            // false here would be noise on every unbounded filter.
            'optionsTruncated' => $this->optionsTruncated ?: null,
            'trueLabel'   => $this->trueLabel,
            'falseLabel'  => $this->falseLabel,
            'trueValue'   => $this->trueValue,
            'falseValue'  => $this->falseValue,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * True when the incoming value is the related entity's own field rather
     * than its identifier — the predicate must then resolve through the
     * relation instead of comparing the association directly.
     */
    private function matchesRelationField(): bool
    {
        return $this->relation !== null && $this->relation->valueField !== 'id';
    }

    /**
     * `SELECT rel FROM Entity rel WHERE rel.valueField = :param` — comparing
     * the association against the subquery's entities lets Doctrine match on
     * identity, whatever the FK column is.
     */
    private function relationSubquery(string $param, bool $in = false): string
    {
        $relation = $this->relation
            ?? throw new \LogicException('A relation subquery needs a filter sourced from a relation.');

        $rel = "rel_{$param}";
        $op = $in ? "IN (:{$param})" : "= :{$param}";

        return "SELECT {$rel} FROM {$relation->entityClass} {$rel} WHERE {$rel}.{$relation->valueField} {$op}";
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        if (is_array($value)) {
            return array_filter($value, static fn ($v) => $v !== null && $v !== '') === [];
        }

        return false;
    }
}
