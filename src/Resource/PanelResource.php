<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Form\Form;
use Modufolio\Panel\Query\DerivedListQuery;
use Modufolio\Panel\Query\ListQueryInterface;
use Modufolio\Panel\Query\QueryInterface;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\JsonApi\JsonApiSerializer;

/**
 * Declarative definition of a panel resource: what it lists, how it queries,
 * how it presents, and what its table looks like.
 *
 * Resources replaced an AbstractPanelController base class. The difference
 * is not cosmetic:
 *
 * - **No inheritance collision.** A controller extending AbstractController
 *   inherits `protected $entityManager`, `$flashBag`, `$urlGenerator`… so
 *   UserController had to surrender five constructor-promoted properties to
 *   move onto the base class. A resource is injected, so nothing collides.
 *
 * - **Readable from outside a request.** `table()` on a controller is
 *   `protected`, so exports, JSON:API and tests can only reach it by issuing
 *   an HTTP request. Here it is a plain public method on a plain object.
 *
 * - **Per-action, not per-class.** One controller can list two resources; a
 *   controller need not be a resource controller at all.
 *
 * A resource is an ordinary service: it declares whatever it needs in its
 * constructor and the host's container builds it. Nothing constructs one
 * behind the container's back — the route loader asks the host for an
 * instance and the host's listing factory does the same — so a resource that
 * consults a workflow or a thumbnail generator simply takes it as an argument.
 */
abstract class PanelResource
{
    /** Route-name prefix and Inertia prop key, e.g. 'organizations'. */
    abstract public function key(): string;

    /** @return class-string */
    abstract public function entityClass(): string;

    /**
     * A hand-written list query, or null to derive one from the table.
     *
     * The derived query — {@see DerivedListQuery} — reads sorting from the
     * columns, search from the `searchable()` ones, the default order from
     * `TableSchema::defaultSort()` and the soft-delete scope from the entity,
     * and is built from the same {@see QueryInterface} objects a class chains.
     * {@see queries()} adds to it. Name a class here when the query is
     * genuinely the resource's own: a search that is more than a LIKE across
     * columns, an ordering that is an expression, a second entity.
     *
     * @return class-string<ListQueryInterface>|null
     */
    public function listQueryClass(): ?string
    {
        return null;
    }

    /**
     * Objects chained onto the list query — the derived one or the class —
     * for the rows and the count alike: a join a column implies nothing
     * about, a condition that is not a permission.
     *
     *     return [new JoinQuery('organization')];
     *
     * Each is a {@see QueryInterface}, the same kind of object a list query
     * class composes with `chain()`. Row-level *permission* is not this:
     * {@see Permissions::scope()}.
     *
     * @param  array<string, mixed> $params as parseListParams() returns them
     * @return list<QueryInterface>
     */
    public function queries(array $params): array
    {
        return [];
    }

    /**
     * Inertia page component, e.g. 'Organizations/Index'.
     *
     * Defaults to the generic listing page, which renders the schema, the
     * pagination and a plain detail drawer from the props alone — so a
     * list-and-drawer resource needs no Vue file and no entry in the manual
     * page registry. Override with a bespoke component as soon as the page
     * needs anything of its own (forms, custom drawer bodies, extra actions).
     */
    public function indexComponent(): string
    {
        return 'Resource/Index';
    }

    /**
     * Shape a page of entities for the table.
     *
     * By default the rows are read off the entities: the id, then each
     * column's key resolved through its path or its `text()` template — see
     * {@see RecordPresenter}. Override when a row needs what no column can
     * say, and return one array per entity with `id` the public identifier.
     *
     * @param  array<int, object> $entities
     * @return array<int, array<string, mixed>>
     */
    public function present(array $entities): array
    {
        return (new RecordPresenter($this))->rows(array_values($entities));
    }

    /**
     * Whether rows come from the default presenter, and so from the columns.
     * The client then reads each cell under the column's own key, since a
     * `value()` path has already been resolved on the server.
     */
    final public function presentsItself(): bool
    {
        return (new \ReflectionMethod($this, 'present'))->getDeclaringClass()->getName() === self::class;
    }

    /**
     * The table: columns, filters, groups, constraints, actions, summaries.
     * See docs/table-schema.md. Null renders no table at all.
     *
     * A column with no label of its own takes the label {@see fields()}
     * declares for its key.
     */
    public function table(): ?TableSchema
    {
        return null;
    }

    /** Trashed filter applied when the request doesn't specify one. */
    public function defaultTrashed(): ?string
    {
        return null;
    }

    public function queryAlias(): string
    {
        return 'e';
    }

    public function showRouteName(): string
    {
        return $this->key() . '_show';
    }

    /**
     * The form, or null for a resource that has none.
     *
     *     return Form::make()->fields([
     *         'title'       => ['width' => '1/2'],
     *         Field::make('director_id')->width('1/2'),
     *         Separator::Line,
     *         'cast',
     *         Field::make('days_until')->computed('daysUntil'),
     *     ]);
     *
     * An entry names a mapped field and states only what the mapping cannot
     * know; one with a type is declared outright and need not be mapped at
     * all. A bare key takes whatever {@see fields()} declares for it first.
     *
     * Returning non-null is also the opt-in for the *generated* write routes:
     * PanelResourceRouteLoader only emits create/edit/delete routes for a
     * resource that declares a form, since without one the generic
     * controller would have nothing to render or validate against.
     */
    public function form(): ?Form
    {
        return null;
    }

    /**
     * What each key is, said once: label, type, options.
     *
     *     return [
     *         Field::make('starts_at')->date()->label('When'),
     *         Field::make('contact')->label('Contact'),
     *     ];
     *
     * The table's columns, the drawer's key list and the form's entries look a
     * bare key up here. Two levels of precedence, no more: what a part says
     * about a key wins over this, and this wins over Doctrine's mapping. A key
     * used in one part only can be declared inline there instead.
     *
     * @return list<Field>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * {@see fields()} as `key => options`, for the parts that merge them.
     *
     * @return array<string, array<string, mixed>>
     */
    final public function fieldDefinitions(): array
    {
        $definitions = [];

        foreach ($this->fields() as $field) {
            $definitions[$field->key()] = $field->toArray();
        }

        return $definitions;
    }

    /**
     * The labels {@see fields()} declares, for a column or a drawer key with
     * none of its own.
     *
     * @return array<string, string>
     */
    final public function fieldLabels(): array
    {
        $labels = [];

        foreach ($this->fieldDefinitions() as $key => $options) {
            if (is_string($options['label'] ?? null) && $options['label'] !== '') {
                $labels[$key] = $options['label'];
            }
        }

        return $labels;
    }

    /**
     * The board, or null for a resource looked at as a table alone.
     *
     *     return Board::make('status')
     *         ->columns(IssueStatus::class)
     *         ->position('position')
     *         ->card('title', 'due_date');
     *
     * Declaring one adds the board beside the table and offers the switcher;
     * the table stays the default and `?view=board` selects the other. A board
     * is a different *query*, not a different renderer over the table's
     * payload, which is why the choice has to reach the server.
     */
    public function board(): ?Board
    {
        return null;
    }

    /**
     * The ways this resource's records can be looked at: the table, then the
     * board when one is declared. The first entry is the default.
     *
     * @return list<ResourceView>
     */
    final public function views(): array
    {
        $board = $this->board();

        return $board === null ? [ResourceView::table()] : [ResourceView::table(), $board->view()];
    }

    /**
     * The view `?view=` selects, or the default.
     *
     * An unknown key falls back to the default rather than failing: a stale
     * bookmark or a view that has since been withdrawn should show the
     * records, not an error page.
     */
    public function viewFor(?string $key): ResourceView
    {
        $views = $this->views();

        if ($views === []) {
            return ResourceView::table();
        }

        foreach ($views as $view) {
            if ($view->key() === $key) {
                $view->assertUsable(static::class);

                return $view;
            }
        }

        $default = $views[0];
        $default->assertUsable(static::class);

        return $default;
    }

    /**
     * DrawerStack slot name for a single record — the `#{type}` template the
     * index page provides. Defaults to the singular of `key()`.
     *
     * Only consulted when the route is generated by PanelResourceRouteLoader;
     * a hand-written controller pushes its own frame and ignores this.
     */
    public function drawerType(): string
    {
        $key = $this->key();

        return str_ends_with($key, 's') ? substr($key, 0, -1) : $key;
    }

    /**
     * Heading shown on that drawer. The default reads the obvious getters
     * before falling back to the record's own identifier, so a resource only
     * overrides this when neither fits.
     */
    public function drawerTitle(object $entity): string
    {
        foreach (['getTitle', 'getName'] as $getter) {
            if (method_exists($entity, $getter)) {
                $value = $entity->{$getter}();

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        $params = $this->recordRouteParams($entity);
        $first  = reset($params);

        return $first === false ? '' : (string) $first;
    }

    /**
     * The resource's entry in the panel's menu, or null for one reached only
     * from elsewhere — another resource's drawer, a dashboard tile.
     *
     *     return Menu::make('Events', icon: 'calendar', group: 'Main', order: 16);
     *
     * Stored on the generated index route and read back by the host's
     * navigation through {@see \Modufolio\Panel\Routing\ResourceMenu}, with the
     * roles that route enforces.
     */
    public function menu(): ?Menu
    {
        return null;
    }

    // ── Permissions ──────────────────────────────────────────────────────────

    /**
     * Who may do what with this resource: roles, operations, rows, fields,
     * board moves. See {@see Permissions} for the layers.
     *
     * A class the application writes, returned here so its dependencies flow
     * through the resource's own constructor — the container builds the
     * resource, the resource builds its permissions. The base class allows
     * everything, so the default is a resource gated by nothing; a resource
     * gated by roles alone returns `new Permissions(['ROLE_USER'])`.
     */
    public function permissions(): Permissions
    {
        return new Permissions();
    }

    /**
     * A singleton resource holds exactly one record — a homepage, a settings
     * blob. The generated listing route skips the table and goes straight to
     * that record; with no record yet, the ordinary listing renders so the
     * empty state can offer creation.
     */
    public function singleton(): bool
    {
        return false;
    }

    /**
     * The drawer for one record: its tabs, each a details grid or a list of
     * related rows. See {@see Drawer}.
     *
     * The default, no tabs, is a single details grid following the form —
     * its fields, in its order — or the columns when there is no form. A
     * details tab with a key list shows exactly those keys, labelled from
     * {@see fields()} and the form.
     */
    public function drawer(): Drawer
    {
        return Drawer::make();
    }

    /**
     * The drawer's tabs as the client renders them for this record: section
     * references resolved, counts read from the record, keys labelled from
     * {@see fields()}.
     *
     * @param  array<string, mixed>       $record     the presented record
     * @param  list<array<string, mixed>> $formFields the resolved form, when the caller has it
     * @return list<array<string, mixed>>
     */
    final public function drawerTabsFor(array $record, array $formFields = []): array
    {
        return DrawerTab::collect($this->drawer()->declaredTabs(), $record, $formFields, $this->fieldLabels());
    }

    /**
     * Detail payload for that drawer.
     *
     * With the default presenter: the row, plus every form key and declared
     * field, so the drawer can show what the form edits. With a presenter of
     * its own: the same shape the table row uses, unless overridden —
     * typically by calling the presenter's `toDetailArray()`.
     *
     * @return array<string, mixed>
     */
    public function presentOne(object $entity): array
    {
        if ($this->presentsItself()) {
            return (new RecordPresenter($this))->record($entity);
        }

        return $this->present([$entity])[0] ?? [];
    }

    /**
     * Route parameters addressing a single record.
     *
     * @return array<string, string|int>
     */
    public function recordRouteParams(object $entity): array
    {
        $uuid = method_exists($entity, 'getUuid') ? $entity->getUuid() : null;

        if (is_string($uuid) || $uuid instanceof \Stringable) {
            return ['uuid' => (string) $uuid];
        }

        throw new \LogicException(sprintf(
            '%s has no getUuid() returning a string or Stringable uuid; '
            . 'override recordRouteParams() to address its records another way.',
            $entity::class,
        ));
    }

    /**
     * Pull search / trashed / sort out of the query params. Override to add
     * resource-specific filters, then read them back in {@see buildListQuery()}.
     *
     * @param  array<string, mixed> $queryParams
     * @return array<string, mixed>
     */
    public function parseListParams(array $queryParams): array
    {
        $filters = JsonApiSerializer::parseFilterParams($queryParams);

        $params = [
            'search'  => $filters['search'] ?? $queryParams['search'] ?? null,
            'trashed' => $filters['trashed'] ?? $queryParams['trashed'] ?? $this->defaultTrashed(),
            'sort'    => JsonApiSerializer::parseSortParams($queryParams) ?: [],
        ];

        // Active grouping, if the schema offers any and the request names one.
        $params['group'] = $queryParams['group'] ?? null;

        // Ad-hoc conditions from the query builder UI.
        $params['constraints'] = is_array($queryParams['constraints'] ?? null)
            ? array_values($queryParams['constraints'])
            : [];

        return $params;
    }

    /**
     * Values for the schema's declared filters, keyed by filter key.
     *
     * Applied *after* {@see parseListParams()} so a subclass's normalisation
     * of a built-in (Organizations clamps `trashed`) is already reflected —
     * collecting these inside parseListParams captured the raw value instead.
     *
     * Only declared keys are read, so an arbitrary query param can never
     * reach the query builder.
     *
     * @param  array<string, mixed> $params      as returned by parseListParams()
     * @param  array<string, mixed> $queryParams
     * @return array<string, mixed>
     */
    final public function filterValues(array $params, array $queryParams): array
    {
        $filters = JsonApiSerializer::parseFilterParams($queryParams);
        $values  = [];

        foreach ($this->table()?->declaredFilters() ?? [] as $filter) {
            $key = $filter->key();

            $values[$key] = array_key_exists($key, $params)
                ? $params[$key]
                : ($filters[$key] ?? $queryParams[$key] ?? null);
        }

        return $values;
    }

    /**
     * The declared list query class, constructed for these params. Only for
     * a resource that names one; the listing derives the query otherwise.
     *
     * @param array<string, mixed> $params
     */
    public function buildListQuery(array $params, ?int $limit, ?int $offset): ListQueryInterface
    {
        $queryClass = $this->listQueryClass();

        if ($queryClass === null) {
            throw new \LogicException(sprintf(
                '%s names no list query class; its query is derived from the table by ResourceListing.',
                static::class,
            ));
        }

        return new $queryClass(
            search: $params['search'],
            trashed: $params['trashed'],
            sort: $params['sort'],
            limit: $limit,
            offset: $offset,
        );
    }

    /**
     * Entries added to the `filters` prop beyond search/trashed/sort.
     *
     * @param  array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function filterProps(array $params): array
    {
        return [];
    }

    /**
     * Read the value of the field being sorted on, for keyset navigation.
     *
     * Resolves the conventional getter by reflection so a resource doesn't
     * maintain a `match` mirroring its sortable-field allowlist.
     */
    public function sortValue(object $entity, string $field): mixed
    {
        $getter = 'get' . ucfirst($field);

        if (!method_exists($entity, $getter)) {
            throw new \LogicException(sprintf(
                '%s declares "%s" as sortable but %s::%s() does not exist. Override sortValue().',
                static::class,
                $field,
                $entity::class,
                $getter,
            ));
        }

        return $entity->{$getter}();
    }
}
