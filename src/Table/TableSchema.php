<?php

declare(strict_types=1);

namespace Modufolio\Panel\Table;

use Modufolio\Panel\Query\ListQueryInterface;

/**
 * Declarative description of a resource's table, authored in PHP and shipped
 * to Vue as an Inertia prop.
 *
 * The point is that a column is described **once**. Before this, "city is
 * sortable" lived in the list query's allowlist *and* in a hand-written
 * `columns` array in the page component, and the two had already drifted
 * (OrganizationListQuery allowed sorting on `phone` while the Vue column
 * declared `sortable: false`).
 *
 * So sortability is not something a column declares — it is **derived** from
 * the list query at serialisation time via
 * {@see ListQueryInterface::mapSort()}. A column can only opt *out*
 * (`->notSortable()`). Drift is impossible by construction: if the query
 * cannot order by a field, its header is not clickable, and there is no
 * second list to forget to update.
 *
 * Usage:
 *
 *     TableSchema::make()
 *         ->columns([
 *             Column::make('name')->linksToRecord()->descriptionKey('status_label'),
 *             Column::make('city')->linksToRecord(),
 *         ]);
 *
 * A linked cell opens the row's record. Where that is, the listing derives
 * from the resource's show route; `->recordUrl()` is for a table whose rows
 * open something else.
 */
final class TableSchema
{
    /** @var list<Column> */
    private array $columns = [];

    /** @var list<Filter> */
    private array $filters = [];

    /** @var list<Group> */
    private array $groups = [];

    /** @var list<Constraint> */
    private array $constraints = [];

    /** @var list<RowAction> */
    private array $actions = [];

    /** @var list<BulkAction> */
    private array $bulkActionList = [];

    /** @var list<ChildTable> */
    private array $children = [];

    private ?string $recordUrl = null;
    private ?string $emptyStateTitle = null;
    private ?string $emptyStateDescription = null;
    private bool $searchable = true;
    private bool $bulkActions = false;
    private bool $stickyHeader = true;

    /** @var array<string, string>|null one `property => ASC|DESC` */
    private ?array $defaultSort = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * @param list<Column> $columns
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Filters offered above the table.
     *
     * @param list<Filter> $filters
     */
    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * Groupings offered to the user.
     *
     * @param list<Group> $groups
     */
    public function groups(array $groups): self
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * Related rows shown under each parent row, one nested table per child.
     *
     * Each child names a to-many association of the listed entity; the
     * listing validates the name against Doctrine's metadata and loads the
     * association for the page in one query. See {@see ChildTable}.
     *
     * @param list<ChildTable> $children
     */
    public function children(array $children): self
    {
        $this->children = $children;

        return $this;
    }

    /** @return list<ChildTable> */
    public function declaredChildren(): array
    {
        return $this->children;
    }

    /** @return list<Group> */
    public function declaredGroups(): array
    {
        return $this->groups;
    }

    /** The group whose key matches, or null. */
    public function group(?string $key): ?Group
    {
        foreach ($this->groups as $group) {
            if ($group->key() === $key) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Fields the user may build ad-hoc conditions against.
     *
     * @param list<Constraint> $constraints
     */
    public function constraints(array $constraints): self
    {
        $this->constraints = $constraints;

        return $this;
    }

    /** @return list<Constraint> */
    public function declaredConstraints(): array
    {
        return $this->constraints;
    }

    /** @return list<Column> */
    public function declaredColumns(): array
    {
        return $this->columns;
    }

    /** @return list<Filter> */
    public function declaredFilters(): array
    {
        return $this->filters;
    }

    /**
     * Replace the filter list — used to swap in relationship-backed options
     * once they have been resolved against the database.
     *
     * @param list<Filter> $filters
     */
    public function withFilters(array $filters): self
    {
        $clone = clone $this;
        $clone->filters = $filters;

        return $clone;
    }

    /**
     * Where a `->linksToRecord()` cell goes, as a template with `{id}` and
     * other dot-path placeholders substituted client-side from the row.
     *
     * Optional: a resource with a show route gets that route's template
     * unless this says otherwise. Declare it when a row opens something
     * other than its own drawer.
     */
    public function recordUrl(string $template): self
    {
        $this->recordUrl = $template;

        return $this;
    }

    public function declaredRecordUrl(): ?string
    {
        return $this->recordUrl;
    }

    /**
     * The schema with a record URL filled in — how ResourceListing hands a
     * table its show route without the resource restating it.
     */
    public function withRecordUrl(string $template): self
    {
        $clone            = clone $this;
        $clone->recordUrl = $template;

        return $clone;
    }

    public function emptyState(string $title, ?string $description = null): self
    {
        $this->emptyStateTitle       = $title;
        $this->emptyStateDescription = $description;

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * The row's own actions, offered in its Actions menu.
     *
     * A listing that declares none keeps whatever its page writes into the
     * `#actions` slot — the slot still wins, so adopting this is additive.
     *
     * @param list<RowAction> $actions
     */
    public function actions(array $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    /**
     * Enable row selection, and optionally declare what can be done with a
     * selection.
     *
     * `bulkActions()` and `bulkActions(true)` mean "render the checkboxes,
     * the page supplies the buttons" — which is what every caller meant
     * before actions could be declared. Passing BulkActions enables selection
     * *and* renders them.
     */
    public function bulkActions(bool|BulkAction ...$actions): self
    {
        if ($actions === []) {
            $this->bulkActions = true;

            return $this;
        }

        foreach ($actions as $action) {
            if (is_bool($action)) {
                $this->bulkActions = $action;
                continue;
            }

            $this->bulkActions      = true;
            $this->bulkActionList[] = $action;
        }

        return $this;
    }

    /** @return list<RowAction> */
    public function declaredActions(): array
    {
        return $this->actions;
    }

    /** @return list<BulkAction> */
    public function declaredBulkActions(): array
    {
        return $this->bulkActionList;
    }

    /**
     * Replace the declared actions — how ResourceListing gates them against
     * what this viewer may actually do, without a resource restating its
     * permissions in the schema.
     *
     * @param list<RowAction>  $actions
     * @param list<BulkAction> $bulkActions
     */
    public function withActions(array $actions, array $bulkActions): self
    {
        $clone                 = clone $this;
        $clone->actions        = $actions;
        $clone->bulkActionList = $bulkActions;
        $clone->bulkActions    = $this->bulkActions || $bulkActions !== [];

        return $clone;
    }

    public function stickyHeader(bool $stickyHeader = true): self
    {
        $this->stickyHeader = $stickyHeader;

        return $this;
    }

    /**
     * The order the listing applies when the request names none.
     *
     * Read by the derived list query; a resource naming its own list query
     * class orders as that class's `defaultSort()` says. `$field` is the
     * entity property, as the query interpolates it.
     */
    public function defaultSort(string $field, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException(sprintf('defaultSort(): direction must be ASC or DESC, got "%s".', $direction));
        }

        $this->defaultSort = [$field => $direction];

        return $this;
    }

    /** @return array<string, string>|null */
    public function declaredDefaultSort(): ?array
    {
        return $this->defaultSort;
    }

    /**
     * Serialise for the client, resolving each column's sortability against
     * the query the listing applies — or, without one, as the columns declare
     * it, for a caller that wants the shape and not the verdict (an export
     * reading headers).
     *
     * @return array<string, mixed>
     */
    public function toArray(?ListQueryInterface $query = null): array
    {
        $columns = array_map(
            fn(Column $column): array => $column->toArray(
                $column->wantsSorting() && ($query === null || $query->mapSort($column->key()) !== null)
            ),
            $this->columns,
        );

        return [
            'columns'               => $columns,
            'filters'               => array_map(static fn(Filter $f): array => $f->toArray(), $this->filters),
            'groups'                => array_map(static fn(Group $g): array => $g->toOption(), $this->groups),
            'constraints'           => array_map(static fn(Constraint $c): array => $c->toArray(), $this->constraints),
            'recordUrl'             => $this->recordUrl,
            'emptyStateTitle'       => $this->emptyStateTitle,
            'emptyStateDescription' => $this->emptyStateDescription,
            'searchable'            => $this->searchable,
            'bulkActions'           => $this->bulkActions,
            'actions'               => array_map(static fn (RowAction $a): array => $a->toArray(), $this->actions),
            'bulkActionItems'       => array_map(static fn (BulkAction $a): array => $a->toArray(), $this->bulkActionList),
            'stickyHeader'          => $this->stickyHeader,
            'children'              => array_map(static fn (ChildTable $c): array => $c->toArray(), $this->children),
        ];
    }
}
