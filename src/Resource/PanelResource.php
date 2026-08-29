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
 * Construction depends on how the resource's routes are declared, and the two
 * contracts differ deliberately:
 *
 * - **Hand-written routes** (`#[Resource(...)]` on a controller action): the
 *   resource is built through the container at request time, so it may declare
 *   constructor dependencies — UserResource takes ImageService this way, via
 *   its config/interfaces.php registration.
 * - **Generated routes** (config/panel_resources.php): the route loader
 *   instantiates the resource at boot, where no container exists, so it must
 *   construct with **no arguments**. This is not a limitation to engineer
 *   around: route building needs only static facts (`key()`, whether a form
 *   is declared), and anything needing services belongs in a presenter or a
 *   request-time hook — where the container still wins — not in the resource's
 *   constructor. A resource that outgrows this graduates to hand-written
 *   routes, keeping its URLs (the loader guarantees the same names and paths).
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
     * Field declarations for the resource's create/edit form, built with the
     * same BlueprintBuilder the page blueprints use — one declaration carries
     * the component, layout, options and validation rules for both sides.
     *
     * Returning non-null is also the opt-in for the *generated* write routes:
     * PanelResourceRouteLoader only emits create/edit/delete routes for a
     * resource that declares a form, since without one the generic
     * ResourceController would have nothing to render or validate against.
     *
     * @return list<array<string, mixed>>|null
     */
    public function formFields(): ?array
    {
        return null;
    }

    /**
     * The lighter alternative to formFields(): name only *which* fields the
     * form edits (plus any overrides), and FormFieldGuesser derives the rest
     * from Doctrine's metadata — types from column types, `max` from column
     * length, `required` from nullability, relations from the association
     * mappings. Declared overrides always win.
     *
     *     return [
     *         'title'       => ['width' => '1/2'],
     *         'director_id' => ['width' => '1/2'],
     *         'cast'        => [],
     *     ];
     *
     * Like formFields(), non-null opts the resource into the generated write
     * routes. When both are implemented, formFields() wins outright.
     *
     * @return array<int|string, string|array<string, mixed>>|null
     */
    public function formFieldKeys(): ?array
    {
        return null;
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

        return (string) reset($this->recordRouteParams($entity));
    }

    // ── Permissions ──────────────────────────────────────────────────────────
    //
    // Four verbs, each answerable about the *type* (no record) or about one
    // record. Route-level roles are the coarse gate and still run first; these
    // are the finer one — "editors may edit only their own", "an admin sees a
    // field an editor must not".
    //
    // The user is passed in rather than fetched, because a resource is a
    // declaration object with no services: generated ones are built without a
    // container, so anything they need has to arrive as an argument.
    //
    // Defaults are permissive, which keeps them additive: a resource that
    // declares nothing behaves exactly as it did before these existed, gated
    // by its routes alone.

    /** May this user see the listing at all, or this record in particular? */
    public function canView(?object $record = null, ?object $user = null): bool
    {
        return true;
    }

    public function canCreate(?object $user = null): bool
    {
        return true;
    }

    public function canEdit(?object $record = null, ?object $user = null): bool
    {
        return true;
    }

    public function canDelete(?object $record = null, ?object $user = null): bool
    {
        return true;
    }

    /**
     * Narrow what the listing can see at all.
     *
     * The counterpart to {@see canView()}: that one answers about a record
     * already in hand, this one keeps records out of reach entirely — which is
     * the only version that also fixes counts, pagination and search. A record
     * excluded here is not merely hidden; {@see \App\Controller\Panel\ResourceController}
     * cannot load it by URL either.
     *
     * @param object $query the resource's list query, to constrain in place
     */
    public function scopeQuery(object $query, ?object $user = null): void
    {
    }

    /**
     * Fields this user may see but not change.
     *
     * Enforced server-side by dropping them from the submission — the client
     * disabling an input is convenience, not the control.
     *
     * @return list<string>
     */
    public function readonlyFields(?object $record = null, ?object $user = null): array
    {
        return [];
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
        return ['uuid' => $entity->getUuid()->toString()];
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
