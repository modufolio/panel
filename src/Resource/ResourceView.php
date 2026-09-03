<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

/**
 * One way of looking at a resource's records.
 *
 * A listing has always rendered exactly one shape — the table. A view names an
 * alternative and says what the server must do differently to serve it, so the
 * choice is a declaration rather than a second page:
 *
 * - {@see table()} — the paginated rows every listing already serves.
 * - {@see board()} — the same records grouped into columns by one field and
 *   ordered within a column by another, which is a different query, not a
 *   different renderer over the same payload.
 *
 * A resource declaring no views gets the table alone, so this is additive.
 *
 * Pure data: a view never touches the database. ResourceListing reads it and
 * builds the query; the client reads it and knows which component to mount.
 */
final class ResourceView
{
    public const TABLE = 'table';

    public const BOARD = 'board';

    private ?string $label = null;

    private ?string $icon = null;

    /** @var list<array{value: string, label: string, color: ?string}> */
    private array $columns = [];

    private ?string $positionField = null;

    private ?string $cardTitle = null;

    /** @var list<string> */
    private array $cardFields = [];

    private int $columnLimit = 50;

    private bool $quickMove = false;

    private function __construct(
        private readonly string $key,
        private readonly string $type,
        private readonly ?string $groupBy = null,
    ) {
    }

    /** The paginated table. Every resource has one; it is the default. */
    public static function table(string $label = 'Table', string $key = self::TABLE): self
    {
        $view = new self($key, self::TABLE);
        $view->label = $label;
        $view->icon  = 'bars-3';

        return $view;
    }

    /**
     * Records as cards in columns, grouped by `$groupBy`.
     *
     * `$groupBy` is a mapped property of the entity — the grouping is compiled
     * into the query, so it addresses the entity, not the presenter's key.
     */
    public static function board(string $groupBy, string $label = 'Board', string $key = self::BOARD): self
    {
        $view = new self($key, self::BOARD, $groupBy);
        $view->label = $label;
        $view->icon  = 'kanban';

        return $view;
    }

    /**
     * The columns, in order, as `value => label` or as a backed enum whose
     * cases are the values.
     *
     * Declared rather than derived from the data: a column with no cards still
     * has to be shown — an empty "Done" is information, and a board that grew
     * its columns from the rows present would hide it.
     *
     * Three ways to say it, because three are already in use here:
     *
     * - an enum class, whose cases are the columns;
     * - a `value => label` map, for columns no enum backs;
     * - a list of `['value' => …, 'label' => …, 'color' => …]`, which is the
     *   shape enums in this codebase already expose as `getColumns()`.
     *
     * @param class-string|array<int|string, mixed> $source
     * @param array<string, string>                 $colors value => colour, for the first two forms
     */
    public function columns(string|array $source, array $colors = []): self
    {
        $clone = clone $this;
        $clone->columns = is_array($source) && self::isColumnList($source)
            ? self::normalizeColumnList($source)
            : self::fromLabels(is_array($source) ? $source : self::enumLabels($source), $colors);

        return $clone;
    }

    /** Already-built column rows, as an enum's own getColumns() returns. */
    private static function isColumnList(array $source): bool
    {
        foreach ($source as $entry) {
            if (!is_array($entry) || !array_key_exists('value', $entry)) {
                return false;
            }
        }

        return $source !== [];
    }

    /**
     * @param  array<int, array<string, mixed>> $source
     * @return list<array{value: string, label: string, color: ?string}>
     */
    private static function normalizeColumnList(array $source): array
    {
        $columns = [];

        foreach ($source as $entry) {
            $value = (string) $entry['value'];

            $columns[] = [
                'value' => $value,
                'label' => (string) ($entry['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $value))),
                'color' => isset($entry['color']) ? (string) $entry['color'] : null,
            ];
        }

        return $columns;
    }

    /**
     * @param  array<int|string, mixed> $labels
     * @param  array<string, string>    $colors
     * @return list<array{value: string, label: string, color: ?string}>
     */
    private static function fromLabels(array $labels, array $colors): array
    {
        $columns = [];

        foreach ($labels as $value => $label) {
            $columns[] = [
                'value' => (string) $value,
                'label' => (string) $label,
                'color' => $colors[$value] ?? null,
            ];
        }

        return $columns;
    }

    /**
     * The property holding a card's place within its column.
     *
     * Required for a board that can be reordered by dragging. Without one the
     * board still renders and still moves cards between columns — it just has
     * no answer for where within a column a card belongs, so it falls back to
     * the list query's own ordering and a drop inside a column is not saved.
     */
    public function position(string $field): self
    {
        $clone = clone $this;
        $clone->positionField = $field;

        return $clone;
    }

    /**
     * What a card shows: its heading, then the presented keys beneath it.
     *
     * Presenter keys, not mapped properties — a card renders what the record
     * was presented as, the same values the table's columns read.
     */
    public function card(string $title, string ...$fields): self
    {
        $clone = clone $this;
        $clone->cardTitle  = $title;
        $clone->cardFields = array_values($fields);

        return $clone;
    }

    /**
     * Cards fetched per column.
     *
     * A board pages by column rather than over the whole set: pagination
     * across a grouped result would cut columns off at arbitrary points, which
     * is why the table's page size cannot simply be reused.
     */
    public function limit(int $cards): self
    {
        if ($cards < 1) {
            throw new \InvalidArgumentException('A board column must show at least one card.');
        }

        $clone = clone $this;
        $clone->columnLimit = $cards;

        return $clone;
    }

    /**
     * Offer a button per card for each column it may move to.
     *
     * Which columns those are is asked of {@see PanelResource::canMoveTo()},
     * per card — so the buttons and the drag answer to the same rule. The
     * board this replaced kept a TRANSITION_MAP in the client and a state
     * machine on the server, and nothing kept the two agreeing.
     *
     * Dragging is the general gesture; these are for the move that is taken
     * often enough to deserve one click, and for touch, where dragging between
     * columns is awkward.
     */
    public function quickMove(): self
    {
        $clone = clone $this;
        $clone->quickMove = true;

        return $clone;
    }

    public function offersQuickMove(): bool
    {
        return $this->quickMove;
    }

    public function label(string $label): self
    {
        $clone = clone $this;
        $clone->label = $label;

        return $clone;
    }

    public function icon(string $icon): self
    {
        $clone = clone $this;
        $clone->icon = $icon;

        return $clone;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isBoard(): bool
    {
        return $this->type === self::BOARD;
    }

    public function groupBy(): ?string
    {
        return $this->groupBy;
    }

    public function positionField(): ?string
    {
        return $this->positionField;
    }

    public function columnLimit(): int
    {
        return $this->columnLimit;
    }

    /** @return list<array{value: string, label: string, color: ?string}> */
    public function columnDefinitions(): array
    {
        return $this->columns;
    }

    /** @return list<string> */
    public function columnValues(): array
    {
        return array_column($this->columns, 'value');
    }

    /**
     * The declaration a board view must satisfy before it is served.
     *
     * A board with no columns renders as an empty page and a board grouping by
     * nothing cannot be built at all — both are declaration bugs, and saying so
     * here is cheaper than a blank screen.
     */
    public function assertUsable(string $resourceKey): void
    {
        if (!$this->isBoard()) {
            return;
        }

        if ($this->groupBy === null || $this->groupBy === '') {
            throw new \LogicException(sprintf(
                'Board view "%s" on %s groups by nothing; pass the property to ResourceView::board().',
                $this->key,
                $resourceKey,
            ));
        }

        if ($this->columns === []) {
            throw new \LogicException(sprintf(
                'Board view "%s" on %s declares no columns. Call ->columns() with an enum class '
                . 'or a value => label map: a board grows its columns from the declaration, not '
                . 'from the rows present, so an empty column is still shown.',
                $this->key,
                $resourceKey,
            ));
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $view = [
            'key'   => $this->key,
            'label' => $this->label,
            'icon'  => $this->icon,
            'type'  => $this->type,
        ];

        if (!$this->isBoard()) {
            return $view;
        }

        return [
            ...$view,
            'groupBy'    => $this->groupBy,
            'columns'    => $this->columns,
            // The client offers drag-to-reorder only when there is somewhere to
            // record the result; without it a drop inside a column would appear
            // to work and be gone on reload.
            'sortable'   => $this->positionField !== null,
            'cardTitle'  => $this->cardTitle,
            'cardFields' => $this->cardFields,
            'limit'      => $this->columnLimit,
            'quickMove'  => $this->quickMove,
        ];
    }

    /**
     * A backed enum's cases as `value => label`, preferring a `label()` method
     * where the enum has one — the same courtesy Filter::select() extends.
     *
     * @param  class-string          $enum
     * @return array<string, string>
     */
    private static function enumLabels(string $enum): array
    {
        if (!enum_exists($enum)) {
            throw new \InvalidArgumentException(sprintf(
                '"%s" is not an enum; pass an enum class or a value => label array.',
                $enum,
            ));
        }

        $labels = [];

        foreach ($enum::cases() as $case) {
            if (!property_exists($case, 'value')) {
                throw new \InvalidArgumentException(sprintf(
                    '%s is not a backed enum, so its cases have no column value.',
                    $enum,
                ));
            }

            // getLabel() first: it is the convention the enums in this
            // codebase already follow, and label() is the one Filament-style
            // enums use. Neither present, the case's own value is humanised.
            $labels[(string) $case->value] = match (true) {
                method_exists($case, 'getLabel') => (string) $case->getLabel(),
                method_exists($case, 'label')    => (string) $case->label(),
                default => ucfirst(str_replace(['_', '-'], ' ', (string) $case->value)),
            };
        }

        return $labels;
    }
}
