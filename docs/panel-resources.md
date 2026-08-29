# Panel resources

A *resource* is a listing in the panel: a table, its filters, its drawer, its
CRUD routes. `Modufolio\Panel\Resource\PanelResource` describes one declaratively; the
`#[Resource]` attribute injects it into a controller action ready to render.

```php
#[Route(path: '/organizations', name: 'organizations', methods: ['GET'])]
public function index(
    #[Resource(OrganizationResource::class)] ResourceListing $listing,
): ResponseInterface {
    return $listing->render();
}
```

That is the whole index action. Filter parsing, pagination, the presenter, the
table schema and the shared props are all inherited from the resource.

---

## Why composition, not a base class

An earlier iteration used an `AbstractPanelController` base class. Two problems
made composition the better answer:

- **Inheritance collides.** A controller extending `AbstractController` inherits
  `protected $entityManager`, `$flashBag`, `$urlGenerator`… so `UserController`
  had to give up five constructor-promoted properties to move onto the base
  class — PHP fatals on redeclaring an inherited `protected` property as
  `private`. An injected resource collides with nothing.
- **A `protected` method is invisible.** `tableSchema()` on a controller could
  only be read by issuing an HTTP request. On a resource it is a public method
  on a plain object, so exports, JSON:API and tests can read it directly.

A resource is also *per-action*, not per-class: one controller can list two
resources, and a controller need not be a resource controller at all.

---

## Defining a resource

Five methods are required:

```php
final class OrganizationResource extends PanelResource
{
    public function key(): string            { return 'organizations'; }        // route prefix + prop key
    public function entityClass(): string    { return Organization::class; }
    public function listQueryClass(): string { return OrganizationListQuery::class; }
    public function indexComponent(): string { return 'Organizations/Index'; }

    public function present(array $entities): array
    {
        return OrganizationPresenter::collection($entities);
    }
}
```

Optional hooks:

| Method | Purpose |
|---|---|
| `tableSchema()` | Columns, filters, groups, constraints — see [table-schema.md](table-schema.md) |
| `queryAlias()` | Query-builder root alias (default `e`) |
| `defaultTrashed()` | Soft-delete scope when the request doesn't specify one |
| `parseListParams()` | Add resource-specific filters to the parsed request |
| `buildListQuery()` | Construct the list query (override when it takes extra arguments) |
| `filterProps()` | Extra entries in the `filters` prop |
| `recordRouteParams()` | Route params addressing one record (default `['uuid' => …]`) |
| `sortValue()` | Read the sort field for keyset navigation |
| `showRouteName()` | Detail route name (default `{key}_show`) |

### Registration

Resources are built through the container. One with **no required constructor
arguments needs no registration** — the resolver instantiates it directly. One
with dependencies goes in `config/interfaces.php`:

```php
UserResource::class => fn () => new UserResource($this->imageService()),
```

The resolver throws a message naming the class and this file if you forget.

---

## `ResourceListing`

What `#[Resource]` injects: the resource bound to the current request.

| Method | Returns |
|---|---|
| `render()` | The Inertia response — rows, pagination, filters, schema, summaries |
| `withDrawer(array $stack)` | A clone with a drawer stack overlaid |
| `withProps(array $props)` | A clone with extra props merged |
| `navigationUrls(object $entity)` | `['next' => …, 'previous' => …]` for arrow-key traversal |

`withDrawer()` and `withProps()` return clones, so an action layers on without
mutating shared state.

### A detail action

Every drawer depth re-renders the *index* component, so the table underneath
keeps its state instead of remounting:

```php
public function show(
    #[MapEntity(mapping: ['uuid' => 'uuid'])] Organization $organization,
    #[Resource(OrganizationResource::class)] ResourceListing $listing,
): ResponseInterface {
    $navigationUrls = $listing->navigationUrls($organization);

    $stack = DrawerStack::create()
        ->push(
            type: 'organization',
            data: $this->presentOrganization($organization),
            title: $organization->getName() ?? '',
            href: $this->urlGenerator->generate('organizations_show', [
                'uuid' => $organization->getUuid()->toString(),
            ]),
            nextRecordUrl: $navigationUrls['next'],
            previousRecordUrl: $navigationUrls['previous'],
        )
        ->toArray();

    return $listing->withDrawer($stack)->render();
}
```

> The `type:` here must match a `#{type}` slot in the page's `<DrawerStack>`.
> They are coupled by string only — a typo renders raw key/value pairs instead
> of your markup, with no error.

On the page, forward the `stack` prop to `<SchemaTable :stack="stack"
drawer-type="organization">` as well — that keeps the highlighted table row in
sync with the record the drawer is showing when the user arrow-keys through
records. See [table-schema.md](table-schema.md#frontend).

### Props emitted by `render()`

| Prop | Contents |
|---|---|
| `{key}` | JSON:API-shaped `{data, meta}`; `meta.summaries` holds column aggregates |
| `filters` | `search`, `trashed`, `sort`, `group`, `constraints`, plus every declared filter's value |
| `stack` | The drawer stack (empty on the index) |
| `table` | The serialised table schema, when the resource declares one |

A prop that is genuinely per-request rather than per-resource is layered on by
the action. `just_approved_url` is a good example — reading it *consumes* a
flash message, so it belongs to the action, not the resource:

```php
return $listing->withProps(['just_approved_url' => $this->getJustApprovedResetUrl()])->render();
```

---

## The list query stays the source of truth

A resource does **not** restate what its `ListQueryInterface` already knows.
The query owns:

- the sortable-field allowlist (`sortableFields()`)
- virtual→entity field mapping (`mapSortField()`, e.g. `name` → `lastName`)
- the default ordering (`defaultSort()`)
- search and soft-delete predicates

Column sortability is *derived* from it at serialisation time, so the two can
never drift. See [table-schema.md](table-schema.md#sortability-is-derived).

Filters whose predicate the query already owns declare
`->handledByQuery()` — the control renders, the query filters. `trashed` uses
this, and so does User's `role`, which matches a JSON `roles` array rather than
a scalar column. Applying it in both places would double-filter.

---

## Gotchas

- **Route ordering.** A `/{uuid}` detail route needs `priority: -1` or it
  swallows `/create`.
- **Prop order with `DefaultProps`.** `DefaultProps::create()` supplies its own
  flash-derived `errors` key. Spread it **first** in any array where you also
  pass `errors`, or yours is silently overwritten and the form renders a
  success-looking 200 with no messages.
- **The resolver pipeline is not memoised.** Several resolvers capture the
  request at construction; caching the pipeline hands later dispatches a stale
  request. Invisible under one-request-per-process, wrong under RoadRunner.

---

## See also

- [adding-a-resource.md](adding-a-resource.md) — the step-by-step recipe, and
  the silent failures worth checking for
- [graduating-a-resource.md](graduating-a-resource.md) — when a resource outgrows
  the generic page
- [table-schema.md](table-schema.md) — columns, filters, groups, constraints
- `controllers.md` (in the reference application) — action parameter resolution
- `presenters.md` (in the reference application) — shaping rows
