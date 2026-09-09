<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Modufolio\Panel\Query\ListQueryInterface;
use Modufolio\Panel\Table\BulkAction;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\ColumnGuesser;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\RowAction;
use Modufolio\Panel\Table\TableSchema;

/**
 * A declared {@see TableSchema} turned into the one the client renders.
 *
 * A resource's `table()` says what it chose to say and no more: a column
 * with no label, a filter with no options, no record URL, no actions. The
 * rest is derived — from `fields()`, from Doctrine's mapping, from the
 * routes that exist and from what this viewer may do — and it is derived
 * here, as a pipeline of `TableSchema → TableSchema` steps that a test can
 * run one at a time without a request or, for all but two, a database.
 *
 * The order is the logic:
 *
 *  1. labels from `fields()` — before the guesser, so a guessed enum badge
 *     keeps the declared wording;
 *  2. the mapping's guesses — types, options, colours a declaration left out;
 *  3. the record URL — from the show route, unless declared;
 *  4. filter defaults and options — the resource's trashed default, then
 *     the relation option lists (the database step);
 *  5. actions — the trio derived from the routes, or the declared ones
 *     minus what this viewer may not do;
 *  6. editability — a control this viewer may not use is not drawn.
 *
 * Every step mutates the schema it is given and returns it, which is safe
 * because {@see PanelResource::table()} builds a fresh one per call.
 */
final class SchemaResolver
{
    public function __construct(
        private readonly ResourceCapabilities $capabilities,
        /** Null skips the mapping's guesses — a test without a database, or a schema over something Doctrine does not map. */
        private readonly ?ColumnGuesser $guesser = null,
        /** Null leaves relation filters without options — a test without a database. */
        private readonly ?FilterOptionResolver $filterOptions = null,
    ) {
    }

    /**
     * The whole pipeline.
     *
     * @param array<string, mixed> $filterValues the filter values in force
     */
    public function resolve(TableSchema $schema, array $filterValues = []): TableSchema
    {
        $schema = $this->resolveLabels($schema);
        $this->guesser?->apply($schema, $this->resource()->entityClass());
        $schema = $this->resolveRecordUrl($schema);
        $schema = $this->resolveFilterDefaults($schema);
        $schema = $this->filterOptions?->resolve($schema, $filterValues) ?? $schema;
        $schema = $this->resolveActions($schema);

        return $this->resolveEditable($schema);
    }

    /**
     * The resolved schema as the client receives it.
     *
     * A resource that presents its own rows has already put each value under
     * the column's key, so the `valueKey` a derived presenter would read is
     * not sent: the client would look for a path the rows do not carry.
     *
     * @return array<string, mixed>
     */
    public function serialise(TableSchema $schema, ?ListQueryInterface $query = null): array
    {
        $table = $schema->toArray($query);

        if ($this->resource()->presentsItself()) {
            foreach ($table['columns'] as &$column) {
                unset($column['valueKey']);
            }
        }

        return $table;
    }

    /**
     * A column with no label of its own takes the one the resource's
     * fields() declares for its key — the same label the form and the drawer
     * show, said once.
     */
    public function resolveLabels(TableSchema $schema): TableSchema
    {
        $labels = $this->resource()->fieldLabels();

        if ($labels === []) {
            return $schema;
        }

        foreach ($schema->declaredColumns() as $column) {
            if (!$column->hasDeclaredLabel() && isset($labels[$column->key()])) {
                $column->label($labels[$column->key()]);
            }
        }

        return $schema;
    }

    /**
     * Where a linked cell goes.
     *
     * A resource with a show route has a record URL whether or not its schema
     * spells one out: the route's own template, `{id}` where the uuid goes.
     * A declared `->recordUrl()` still wins, for a listing whose rows open
     * something other than their own drawer. What is refused is a column
     * that links to the record while nothing says where — that used to
     * render as a row that looked clickable and did nothing, with no error
     * anywhere. Child tables have no route to derive from, so they are only
     * checked.
     */
    public function resolveRecordUrl(TableSchema $schema): TableSchema
    {
        if ($schema->declaredRecordUrl() === null && ($template = $this->capabilities->template('show')) !== null) {
            $schema = $schema->withRecordUrl($template);
        }

        if ($schema->declaredRecordUrl() === null) {
            $this->assertNoRecordLinks($schema->declaredColumns(), sprintf(
                '%s\'s table has no record URL: generate its show route, or declare ->recordUrl() on the TableSchema.',
                $this->resource()::class,
            ));
        }

        foreach ($schema->declaredChildren() as $child) {
            if ($child->declaredRecordUrl() === null) {
                $this->assertNoRecordLinks($child->declaredColumns(), sprintf(
                    'child table "%s" has no record URL: declare ->recordUrl() on the ChildTable.',
                    $child->key(),
                ));
            }
        }

        return $schema;
    }

    /**
     * The trashed control's default is the resource's decision, not the
     * schema's: a resource listing deleted rows by default hands the client
     * that value so it shows without counting as a filter the viewer
     * applied, and a reset returns to it.
     */
    public function resolveFilterDefaults(TableSchema $schema): TableSchema
    {
        $default = $this->resource()->defaultTrashed();

        if ($default === null) {
            return $schema;
        }

        return $schema->withFilters(array_map(
            static fn (Filter $filter): Filter => $filter->type() === Filter::TRASHED && $filter->defaultValue() === null
                ? $filter->withDefault($default)
                : $filter,
            $schema->declaredFilters(),
        ));
    }

    /**
     * Decide which row and bulk actions this viewer is actually offered.
     *
     * A resource that declares none gets the standard trio, derived from
     * whether the routes exist — the same rule the generic Resource/Index
     * page applied in markup, moved to where the routes and the permissions
     * both live. A resource that declares its own keeps them, minus the ones
     * this viewer may not perform: gating in the schema means a page cannot
     * offer what the server would refuse.
     */
    public function resolveActions(TableSchema $schema): TableSchema
    {
        $actions = $schema->declaredActions();

        if ($actions === []) {
            // Nothing declared: derive the trio from the routes that exist.
            // Route existence is the resource's answer to "what can be done
            // here" only when it has given no other answer.
            $actions = $this->defaultRowActions();
        } else {
            // A declared action names its own URL, so the route behind it is
            // the resource's business — gating on a `{key}_destroy` name here
            // silently dropped Delete from every listing whose controller
            // named it something else. Permission is the only question left.
            $permissions = $this->capabilities->permissions();
            $user        = $this->capabilities->user();
            $canEdit     = $permissions->edit(null, $user);
            $canDelete   = $permissions->delete(null, $user);

            $actions = array_values(array_filter(
                $actions,
                static fn (RowAction $action): bool => match ($action->name()) {
                    'edit' => $canEdit,
                    // Restore rides the delete permission: both govern the
                    // same trash lifecycle, and offering one without the
                    // other strands a record where the viewer put it.
                    'delete', 'restore' => $canDelete,
                    default => true,
                },
            ));
        }

        $bulkActions = $schema->declaredBulkActions();

        // Bulk delete rides the same rule as the row's: nothing declared, a
        // route that exists, a viewer allowed to delete the type.
        $mayDelete = $this->capabilities->permissions()->delete(null, $this->capabilities->user());

        if ($bulkActions === [] && $mayDelete && ($bulkUrl = $this->capabilities->url('bulk_destroy')) !== null) {
            $bulkActions = [BulkAction::delete($bulkUrl)];
        }

        return $schema->withActions($actions, $bulkActions);
    }

    /**
     * A control this viewer may not use is not drawn.
     *
     * The patch endpoint refuses a field the viewer may not write; before
     * this the listing drew the control anyway, so the refusal arrived after
     * the click. Asked at the type level here — the same `writable()` the
     * endpoint asks, without a record — so a rule about *this* record still
     * answers on the write, and a rule about this *viewer* answers before
     * the control exists. A viewer who may not edit the type at all gets no
     * inline control either.
     */
    public function resolveEditable(TableSchema $schema): TableSchema
    {
        $editable = array_filter($schema->declaredColumns(), static fn (Column $column): bool => $column->isEditable());

        if ($editable === []) {
            return $schema;
        }

        $permissions = $this->capabilities->permissions();
        $user        = $this->capabilities->user();
        $mayEdit     = $permissions->edit(null, $user);

        foreach ($editable as $column) {
            if (!$mayEdit || !$permissions->writable($column->field(), $user)) {
                $column->editable(false);
            }
        }

        return $schema;
    }

    private function resource(): PanelResource
    {
        return $this->capabilities->resource();
    }

    /**
     * View / Edit / Delete, each only when its route exists and this viewer
     * may use it.
     *
     * @return list<RowAction>
     */
    private function defaultRowActions(): array
    {
        $actions = [];

        if ($this->capabilities->has('show')) {
            $actions[] = RowAction::view();
        }

        if ($this->capabilities->edit() && ($edit = $this->capabilities->template('edit')) !== null) {
            $actions[] = RowAction::edit($edit);
        }

        if ($this->capabilities->delete() && ($destroy = $this->capabilities->template('destroy')) !== null) {
            $delete = RowAction::delete($destroy);

            // Consequences instead of a blind guarantee, when the resource has
            // somewhere to ask.
            if (($preview = $this->capabilities->template('delete_preview')) !== null) {
                $delete = $delete->previewUrl($preview);
            }

            $actions[] = $delete;
        }

        return $actions;
    }

    /**
     * @param list<Column> $columns
     */
    private function assertNoRecordLinks(array $columns, string $remedy): void
    {
        $linking = array_values(array_filter(
            $columns,
            static fn (Column $column): bool => $column->wantsRecordLink(),
        ));

        if ($linking === []) {
            return;
        }

        throw new \LogicException(sprintf(
            'Column "%s" links to the record, but %s',
            implode('", "', array_map(static fn (Column $column): string => $column->key(), $linking)),
            $remedy,
        ));
    }
}
