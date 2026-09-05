<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Modufolio\Panel\Query\ListQueryInterface;
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
 * - **Readable from outside a request.** `tableSchema()` on a controller is
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

    /** @return class-string<ListQueryInterface> */
    abstract public function listQueryClass(): string;

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
     * @param  array<int, object> $entities
     * @return array<int, array<string, mixed>>
     */
    abstract public function present(array $entities): array;

    public function tableSchema(): ?TableSchema
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
     * The resource's create/edit form: its entries, in display order.
     *
     * Most entries name a mapped field and state only what the mapping cannot
     * know. FormFieldGuesser reads the type from the column, `max` from its
     * length, `required` from its nullability and relations from the
     * associations, then merges the entry's options on top — declared options
     * always win.
     *
     *     return [
     *         'title'       => ['width' => '1/2'],
     *         'director_id' => ['width' => '1/2'],
     *         Separator::Line,
     *         'cast',
     *         'notes'       => ['type' => TextareaType::class, 'help' => '…'],
     *         'days_until'  => ['type' => ComputedType::class, 'accessor' => 'daysUntil'],
     *     ];
     *
     * An entry with a `type` is declared outright: the type wins over the
     * column and over a `#[FormType]` attribute, and the key need not be
     * mapped at all — a set, an embed, a computed value, a hidden import
     * reference. What the mapping does know still applies to a mapped key, so
     * a `TextareaType` over a string column keeps the column's `max`.
     * Per-field `access` callables are options like any other; they never
     * reach the client (see {@see \Modufolio\Panel\Blueprint\FieldAccess}). A
     * {@see \Modufolio\Panel\Blueprint\Separator} entry draws a rule, or
     * leaves a gap, across the row.
     *
     * Returning non-null is also the opt-in for the *generated* write routes:
     * PanelResourceRouteLoader only emits create/edit/delete routes for a
     * resource that declares a form, since without one the generic
     * ResourceController would have nothing to render or validate against.
     *
     * @return array<int|string, string|\Modufolio\Panel\Blueprint\Separator|array<string, mixed>>|null
     */
    public function formFields(): ?array
    {
        return null;
    }

    /**
     * The ways this resource's records can be looked at.
     *
     * The default is the table alone, which is what every listing served
     * before views existed. Declaring more offers a switcher in the listing
     * header and lets `?view=` select one:
     *
     *     public function views(): array
     *     {
     *         return [
     *             ResourceView::table(),
     *             ResourceView::board('status')
     *                 ->columns(IssueStatus::class)
     *                 ->position('position')
     *                 ->card('title', 'due_date'),
     *         ];
     *     }
     *
     * The first entry is the default. A board is a different *query*, not a
     * different renderer over the table's payload, which is why the choice has
     * to reach the server rather than living in the client.
     *
     * @return list<ResourceView>
     */
    public function views(): array
    {
        return [ResourceView::table()];
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
     * Sections the drawer offers for a single record.
     *
     * Returning none — the default — keeps the flat definition grid, which is
     * all a resource without child collections needs. Declaring tabs is how a
     * generated resource gets what the bespoke drawers have: a details grid
     * plus lists of related rows, each with its own count.
     *
     * The lists read keys the resource's own {@see presentOne()} returns, so a
     * tab costs no extra query — and the grid keeps dropping arrays, which is
     * exactly the content the relation tabs now render honestly.
     *
     * @return list<DrawerTab>
     */
    public function drawerTabs(): array
    {
        return [];
    }

    /**
     * Detail payload for that drawer.
     *
     * Defaults to the same shape the table row uses; a resource with a richer
     * detail view (extra relations, a body field) overrides this — typically
     * by calling its presenter's `toDetailArray()`.
     *
     * @return array<string, mixed>
     */
    public function presentOne(object $entity): array
    {
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

        foreach ($this->tableSchema()?->declaredFilters() ?? [] as $filter) {
            $key = $filter->key();

            $values[$key] = array_key_exists($key, $params)
                ? $params[$key]
                : ($filters[$key] ?? $queryParams[$key] ?? null);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function buildListQuery(array $params, ?int $limit, ?int $offset): ListQueryInterface
    {
        $queryClass = $this->listQueryClass();

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
