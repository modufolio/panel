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

Four methods are abstract:

```php
final class OrganizationResource extends PanelResource
{
    public function key(): string            { return 'organizations'; }        // route prefix + prop key
    public function entityClass(): string    { return Organization::class; }
    public function listQueryClass(): string { return OrganizationListQuery::class; }

    public function present(array $entities): array
    {
        return OrganizationPresenter::collection($entities);
    }
}
```

`indexComponent()` defaults to the generic `Resource/Index` page, which renders
the write actions too — override it only for a resource that has outgrown it
(see [graduating-a-resource.md](graduating-a-resource.md)).

### The listing

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
| `singleton()` | The resource holds exactly one record — the listing route opens it directly, falling back to the ordinary listing while none exists, so its empty state can offer creation |

### The record

| Method | Purpose |
|---|---|
| `presentOne()` | The drawer's payload (defaults to the first entry of `present()`) |
| `drawerTabs()` | The drawer's sections — without them the details grid prints every presenter key |
| `drawerType()` | Slot name for one record (default: the singular of `key()`) |
| `drawerTitle()` | Heading for the open record |

### The form

| Method | Purpose |
|---|---|
| `formFields()` | The form's entries in order: mapped fields with options, fields declared outright by `type`, separators. The rest is guessed from Doctrine; per-field `access` rides along as an option |

Returning non-null is the opt-in for the generated create/edit/delete routes.
See [fields.md](fields.md).

### Permissions

Who may do what is one class the application writes, extending
`Resource\Permissions`, and the resource returns it from `permissions()`:

```php
public function permissions(): Permissions
{
    return new EventPermissions($this->workflow);   // or: new Permissions(['ROLE_USER'])
}
```

The base class allows everything and names no role, so a resource gated by
its routes alone returns `new Permissions([...roles])` and writes no class.
One that refuses anything overrides the method for it:

| Method | Scope |
|---|---|
| `roles()` | Route — stored on every generated route as `_is_granted_roles`, enforced by the kernel with the role hierarchy |
| `view()` / `create()` / `edit()` / `delete()` / `export()` | Operation, about the type (no record) or one record |
| `scope($qb, $alias, $user)` | Row — what the listing, its counts and the record lookup can see at all |
| `readable($field, $user, $record)` / `writable($field, $user, $record)` | Field, per user and per record — see [fields.md](fields.md#per-field-access) |
| `move($record, $lane, $user)` | Board — which lanes a card may be dragged into; a string is the refusal shown |

A class rather than hooks on the resource, because rules are behaviour: typed
record, typed user, testable without a resource, reusable through a base class
(`TenantPermissions` carrying the scope for every resource that shares it),
and free to take a service — the resource's constructor is where it arrives.
Which operations *exist* stays structural (`formFields()`, `only()`,
`except()`); permissions only ever narrow what exists.

The layers are easy to reason about one at a time and hard to see combined.
`Inspection\PermissionInspector` reads them back together without a request:
per resource and per role, which generated routes admit the role, what the
permissions answer for a stand-in user, which methods the class overrides
(and so may answer per record), and which fields are readable, read-denied or
write-denied — plus notes on divergences, such as a rule that reads a literal
role while the route layer honours the hierarchy. The host wires it with its
route collection, its resource factory, a `FormResolver`, the role hierarchy
and a user factory, and can expose it as a console command, a page, or both.

### Registration

A resource is a service, and the application's container builds it — for the
`#[Resource]` resolver, for the generic controller, and for the route loader
alike. Register every resource in the application's service definitions,
declaring whatever it needs
through its constructor:

```php
->set(ActorResource::class, fn () => new ActorResource())
->set(UserResource::class, fn (App $app) => new UserResource($app->imageService()))
->set(IssueResource::class, fn (App $app) => new IssueResource($app->get(StateMachine::class)))
```

There is no second way in. The route loader is handed a resolver by the host
(a closure over the container) and never calls `new` itself, so a resource with
constructor dependencies generates routes exactly like one without. Route
collections load lazily, on the router's first use, by which point the
container exists.

A resource that is not registered fails with the container's own not-found
message, naming the class, the first time anything asks for it.

---

## Views

A listing renders one shape by default: the table. `views()` names others.

```php
public function views(): array
{
    return [
        ResourceView::table(),
        ResourceView::board('status')
            ->columns(IssueStatus::class)   // or a value => label map, or an
                                            // already-built column list
            ->position('position')
            ->card('title', 'due_date'),
    ];
}
```

The first entry is the default, and `?view=<key>` selects another. The client
renders a switcher only when there is more than one, so adding `views()` to a
resource that declares just the table changes nothing.

**A board is a different query, not a different renderer.** It runs one query
per declared column, ordered by the position field and limited per column —
which is why the choice reaches the server rather than living in the client. A
single `LIMIT` across a grouped result would cut columns off at arbitrary
points: the fifth column arrives empty not because it is empty, but because the
first four used up the page.

Columns come from the declaration, never from the rows present. A board that
grew its columns from the data would hide an empty "Done" — the column whose
emptiness is most worth seeing.

### Positions

`->position($field)` names the property holding a card's place within its
column. It must be a **`bigint`**, and the positions in it are sparse:

```php
#[ORM\Column(type: 'bigint', options: ['default' => 0])]
private int $position = 0;
```

A card's *index* cannot express "between these two" without renumbering
everything below the drop — a full-column write on every drag, and a race where
two people dragging at once overwrite each other. `BoardPosition` leaves gaps
instead: cards start `2^32` apart, a drop takes the midpoint of the gap, and
only the moved row is written. A small random offset keeps two simultaneous
drops into the same gap from landing on the same value.

Integers rather than decimals, deliberately. An earlier version used
`DECIMAL(20,10)` and bcmath, which bought exact midpoints that the storage then
threw away: SQLite gives a decimal column REAL affinity, so positions
round-tripped through a double and two values the server computed as distinct
came back **equal** — silently, which is the one failure the scheme exists to
prevent. An integer is exact everywhere and needs no extension.

The gap affords 32 drops into the same spot before it closes, and a column
holds two billion cards. When a gap does close, `BoardMover` spreads the column
back onto even spacing and places the card again — an arithmetic limit nobody
can see must not surface as a refused drag.

Without a position field the board still renders and still moves cards between
columns; it just declares itself `sortable: false`, and the client does not
offer reordering within a column — rather than offering it and losing it on
reload.

### Moves

A drag posts to `{key}_board_move` with the target column and the ids of the
cards either side of the drop. The client never sends a position: only the
server sees two people dropping into the same gap.

The endpoint asks the permissions' `edit()`, then their `move()`:

```php
public function move(object $record, string $lane, ?object $user): bool|string
{
    // true to allow, or a message explaining the refusal
}
```

The default allows every move, because most boards are a plain grouping and
dragging is just editing that field. Override it where the columns are workflow
states — a board that lets a card be dragged from Backlog to Done and only then
discovers there is no such transition has already lied to the person dragging
it. Return the message rather than a bare `false`: it is what the board shows
when it puts the card back.

`BoardMover` independently refuses a column the view does not declare. That
check is what protects a resource using the default `move()`, where the
declaration is the only thing between a dropped card and an arbitrary value.

### Quick-move buttons

`->quickMove()` puts a button on each card for every column it may move to.
The targets are computed per card from the permissions' own `move()`, so a
button is offered exactly when the move behind it would be accepted — and a
guarded transition disappears while its guard blocks. Dragging stays the
general gesture; the buttons are for the move taken often enough to deserve one
click, and for touch, where dragging between columns is awkward.

They arrive as `columns[].moves`, keyed by card id, alongside `cards` rather
than inside them — so `cards` stays exactly what `present()` returned.

The client reads `resource.canMove`, **not** `resource.canEdit`. `canEdit` also
requires the edit *form* route, and a board needs no form — it groups records by
a field they already have. `canMove` is the move route plus the edit
permission, which is what the endpoint itself checks. Wiring the drag to
`canEdit` makes every card on a formless board immovable while the endpoint
behind it works perfectly.

---

## Exports

The table's Export button posts the current result set to the resource's export
route and gets a CSV, Excel, JSON or print payload back. Three things about it
are worth knowing before you rely on it, because none is guessable from the
button.

### Who may export

**Being allowed to view a listing and being allowed to download it are the same
permission.** The generated export route asks the permissions' `export()`,
which follows `view()` unless overridden — a role that reaches the listing
can export every row it can see.

That is deliberate: a download is a read, and a listing that renders 500 rows
on screen has already disclosed them. A resource that needs a stricter rule
overrides `export()`; nothing else is consulted.

`scope()` still applies, so an export can never reach rows the listing itself
could not.

### Which columns

The **client names the columns**, in the request body:

```jsonc
{ "format": "json",
  "columns": [ { "key": "title", "label": "Title" } ] }
```

The table schema is only the fallback for when the body names none — the client
sends its list because it knows which columns are actually on screen, filtered
and reordered.

The consequence: **the column list is caller-supplied, not server-chosen.** A
request may name any key, including one the table never renders. What comes
back is whatever `present()` emits under that key, so the presenter — not the
table schema — is the real boundary.

### Which values

Exports present through `present()` (the **list** shape), not `presentOne()`
(the **record** shape):

| Path | Presenter | Typically carries |
|---|---|---|
| Listing, export | `present()` | The columns a table row needs |
| Drawer, detail, edit | `presentOne()` | The above plus the form's fields |

A key named in the export body that `present()` does not emit exports as
`null` — for everybody, including an admin.

> **`access` does not reach here.** Per-field `access.read`
> ([fields.md](fields.md#per-field-access)) governs *form definitions*; the
> export path never consults it. A field kept out of an export today is kept
> out because `present()` omits it, not because anyone was denied. Promote such
> a field to `present()` for a list column and every role that can view the
> listing can export it the same hour. If a value must never leave by this
> door, keep it out of `present()` — and pin that with a test, since the next
> person to add a column has no way to see the rule.

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
> They are coupled by string only. A typo renders raw key/value pairs instead
> of your markup; `DrawerStack` warns in the browser console, once per
> unclaimed type, so the fallback is not mistaken for the drawer you meant.

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
- **A board's position column must be `bigint`.** A 32-bit integer column
  overflows after a few hundred cards, since positions are `2^32` apart by
  design. Do not "tidy" the values into 1, 2, 3 — the gaps are the mechanism,
  and closing them turns every drag back into a full-column rewrite.
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
- [fields.md](fields.md) — blueprint forms: field types, conditions, defaults,
  per-field access
