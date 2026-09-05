# Adding a resource

A generated resource is a listing, a drawer and (optionally) a form, with **no
controller, no `#[Route]` and no Vue file**. You write four small PHP classes
and two config entries; `PanelResourceRouteLoader` and `ResourceController` do
the rest.

This is the recipe. [panel-resources.md](panel-resources.md) explains *why* the
design is shaped this way; this page is what to type, in order, and what fails
silently if you skip a step.

The worked example throughout is an `EventResource` — every event across
every contact, read-only, reached from the sidebar. Every path in this recipe (`src/Entity/…`, `config/…`) is a path
in the **consuming application**, not in this package: a resource is something
an application declares, and the package is what turns the declaration into a
listing.

---

## The checklist

Nine steps. Six of them are files; three are one-line registrations that are
easy to forget because nothing errors when you do.

| # | What | Where |
|---|------|-------|
| 1 | Entity, with a `uuid` | `src/Entity/{Name}.php` |
| 2 | Repository | `src/Repository/{Name}Repository.php` |
| 3 | Register the repository | `config/repositories.php` |
| 4 | Migration | `database/migrations/Version{ts}.php` |
| 5 | List query | `src/Query/{Name}ListQuery.php` |
| 6 | Presenter | `src/Presenter/{Name}Presenter.php` |
| 7 | Resource | `src/Panel/{Name}Resource.php` |
| 8 | Register the resource, and its menu entry | `config/services.php` and `config/panel_resources.php` |

Then tests. See [Verifying it](#verifying-it) — the assertions worth writing
are the ones that catch the silent failures below.

---

## 1. The entity needs a `uuid`

Generated routes address a record by its **public uuid**, never its row number:

```php
// ResourceController::find()
->where("{$alias}.uuid = :uuid")
```

An entity without one produces a listing that works and a drawer that 500s. So:

```php
#[ORM\Column(type: 'uuid', unique: true)]
private UuidInterface $uuid;

public function __construct()
{
    $this->uuid = Uuid::uuid4();
}

public function getUuid(): UuidInterface
{
    return $this->uuid;
}
```

Add `Timestampable` if the drawer should be able to say when a record was
added.

> **Adding a uuid to a table that already has rows?** Backfill *before*
> creating the unique index, or the existing rows end up unaddressable. See
> `Version20260826140000` for the pattern.

---

## 2–3. Repository, and registering it

A plain `EntityRepository` subclass with whatever finders the panel needs.
Then wire it in `config/repositories.php` — **two imports and a map entry**:

```php
use App\Entity\Event;                  // ← easy to miss
use App\Repository\EventRepository;    // ← easy to miss

return [
    EventRepository::class => Event::class,
];
```

Skip this and injection fails at runtime, not at boot.

---

## 4. Migration

`php bin/console migrations:migrate --no-interaction`. Index what the listing
orders and filters on — for events that is `(contact_id, starts_at)`, because
every read is "this contact's events, by date".

---

## 5. The list query

Extends `Modufolio\Panel\Query\AbstractListQuery`, which the package ships —
every consumer used to re-implement the same base class. It owns sorting,
search and eager loading:

```php
final class EventListQuery extends AbstractListQuery
{
    // Public column name → entity property, where they differ.
    protected const FIELD_MAPPING = ['when' => 'startsAt'];

    // Only these may be sorted. A column not listed here renders unsortable.
    protected const SORTABLE_FIELDS = ['title', 'startsAt', 'endsAt', 'createdAt'];

    public static function defaultSort(): array
    {
        return ['startsAt' => 'ASC'];
    }

    protected function applyFilters(QueryBuilder $qb): QueryBuilder { /* search */ }

    /** The row names its contact, so select the joined alias — else N+1. */
    protected function applyEagerLoads(QueryBuilder $qb): QueryBuilder
    {
        return $qb->addSelect('contact');
    }
}
```

Join **unconditionally** rather than only when searching, so the listing and its
count share one predicate.

---

## 6. The presenter

Shapes a row. One rule: **`id` is the uuid.** The internal integer never leaves
the backend.

```php
public function toArray(): array
{
    return [
        'id'    => $this->event->getUuid()->toString(),
        'title' => $this->event->getTitle(),
        'when'  => $this->event->getWhenLabel(),
    ];
}

/** The drawer's payload — toArray() plus whatever only a detail view shows. */
public function toDetailArray(): array
{
    return [...$this->toArray(), 'notes' => $this->event->getNotes()];
}
```

Formatting belongs here, not in the Vue layer: the generic drawer prints values
as it receives them.

---

## 7. The resource

Four are abstract; `queryAlias()` and `presentOne()` have defaults worth
overriding here (`e` labels nothing, and a drawer usually shows more than a
row). Then the schema:

```php
final class EventResource extends PanelResource
{
    public function key(): string           { return 'events'; }          // → /panel/events
    public function entityClass(): string   { return Event::class; }
    public function listQueryClass(): string{ return EventListQuery::class; }
    public function queryAlias(): string    { return 'event'; }
    public function present(array $e): array{ return EventPresenter::collection($e); }
    public function presentOne(object $e): array
    {
        return EventPresenter::make($e)->toDetailArray();
    }
```

### The table schema

```php
    public function tableSchema(): TableSchema
    {
        return TableSchema::make()
            ->emptyState('No events yet', '…')
            ->filters([...])
            ->columns([
                Column::make('title')->linksToRecord() // which cells open the record
                    ->weight('medium'),
                Column::make('when')->linksToRecord()->label('When'),
            ]);
    }
```

`linksToRecord()` says which cells open the row's record. *Where* the record
is comes from the resource's generated show route, so nothing else has to be
declared; `->recordUrl('/panel/…/{id}')` on the schema overrides that for a
table whose rows open something other than their own drawer. A linking column
on a resource with neither is refused when the listing renders, naming the
column, rather than shipped as a row that looks clickable and does nothing.

### The drawer

```php
    public function drawerTabs(): array
    {
        return [
            DrawerTab::details()->fields([
                'when'     => 'When',
                'contact'  => 'Contact',
                'location' => 'Location',
            ]),
        ];
    }
```

Omit this and the drawer prints **every key the presenter returned**, including
the ones that exist to make the row render — a `contact_id` that addresses a
link target, a `has_passed` that dims a past row. Name the fields.

### Read-only or writable

Write routes — create, edit, update, delete — are generated **only if
`formFields()` returns non-null**:

```php
// Writable: the fields, with any layout or rules the mapping cannot infer.
public function formFields(): array
{
    return ['name' => ['width' => '1/2'], 'birth_year' => ['width' => '1/2']];
}
```

So a read-only resource is one that simply declares no form fields. A
permissions class answering `false` to `create()`/`edit()`/`delete()` on top
of that is belt-and-braces: it states the intent, and keeps deletes off if
someone later adds form fields to make the listing editable.

Everything the form can declare — conditions, defaults, validation messages,
and the field types a guessed form cannot reach — is in [fields.md](fields.md).

### Who may do what

The roles the routes require, and every finer rule, live on one class the
resource names:

```php
    public function permissions(): Permissions
    {
        return new Permissions(['ROLE_USER']);      // roles alone: no class to write
    }
```

The base class allows everything else. A resource that refuses something
extends it — `EventPermissions` with a `delete()` that says no, a `scope()`
that narrows rows to the viewer's tenant, a `writable()` that freezes a field
on a closed record — and returns `new EventPermissions()` instead. See
[panel-resources.md](panel-resources.md#permissions) for the full method list.

---

## 8. Register the resource

Twice, for two different questions.

`config/services.php` says how the class becomes an instance. A resource is
an ordinary service — the container is the only thing that constructs one, so
whatever it needs arrives through its constructor:

```php
->set(EventResource::class, fn () => new EventResource())
// or, with a collaborator:
->set(EventResource::class, fn (App $app) => new EventResource($app->imageService()))
```

Skip this and the first request for the resource fails with the container's
not-found message, naming the class.

`config/panel_resources.php` says which routes it gets:

```php
$panel->resource(EventResource::class);
```

The roles its permissions name land on every generated route as
`_is_granted_roles`, enforced by the kernel before `ResourceController` runs —
no guard clause needed, and no second role list at registration.

---

## 9. Give it a menu entry

Where the resource is registered:

```php
$panel->resource(EventResource::class)
    ->menu('Events', icon: 'calendar', group: 'Main', order: 16);
```

The entry is stored on the generated index route and read back by
`Routing\ResourceMenu::fromRoutes()`, which the host's navigation renders
beside its hand-written entries. The route's own roles gate it, so there is no
second role list to keep in step — a viewer the kernel admits sees the entry,
one it refuses does not.

Icon names come from the panel's built-in set (`ui/src/Components/Core/Icon.vue`
in this package) or anything the app registered via `registerIcons()`. A
resource registered without `menu()` still works; it is simply not linked from
the sidebar, which is right for a resource only reached from another's drawer.

---

## Silent failures

Every one of these was hit while building `EventResource`. None of them throws.

| Symptom | Cause |
|---------|-------|
| Rows render but nothing in them is clickable | No column has `->linksToRecord()` |
| `Column "title" links to the record, but … has no record URL` at render | The resource generates no show route (`->only([...])` without `'show'`) and declares no `->recordUrl()`; add either |
| Drawer shows `Contact Id`, `Has Passed` as if they were fields | No `drawerTabs()` — the details grid prints every presenter key |
| Route works, nothing in the panel links to it | No `->menu(...)` on the registration |
| The area file exists, an admin still sees no menu item | Its `roles` are intersected literally, with no role hierarchy — a `ROLE_SUPER_ADMIN` does not match an area gated on `ROLE_ADMIN`, though the *route* admits them. Name both |
| A `when` condition or a `readable()`/`writable()` rule that guards nothing | The write path bypasses `SubmissionHandler` and never calls `stripHidden()` / `stripDenied()` itself — see [fields.md](fields.md#wiring-the-guards) |
| Listing fine, drawer errors | Entity has no `uuid` |
| A create button that opens an empty form | `formFields()` names keys the presenter/entity does not carry |
| The form shows `Unknown field type "x"` with a `createPanel` snippet | A PHP `FieldType` emits a component the client neither ships nor the app registered; register it as the snippet says. `Field\FieldComponents::missing()` finds this before a page does — the reference application's `panel:lint` runs it over every resource |
| A column renders as a raw value | Its `type` has no case in `SchemaTable.vue` — or register one with `registerColumnType()` |

---

## Verifying it

The failures above are all visible in the Inertia props, so they can be
asserted without a browser. `test/EventResourceTest.php` is the template:

```php
// The listing exists and is gated.
$this->get('/panel/events')->assertRedirect('/panel/login');
$this->login();
$this->get('/panel/events', headers: $this->inertiaHeaders())
    ->assertStatus(200)
    ->component('Resource/Index');

// Rows point somewhere, and a cell opts into being the link.
$table = $this->get('/panel/events', headers: $this->inertiaHeaders())
    ->jsonData()['props']['table'];

$this->assertSame('/panel/events/{id}', $table['recordUrl']);
$linking = array_column(
    array_filter($table['columns'], static fn (array $c): bool => $c['linksToRecord'] === true),
    'key',
);
$this->assertContains('title', $linking);

// The drawer opens, and shows only what was curated.
$props = $this->get('/panel/events/' . $uuid, headers: $this->inertiaHeaders())
    ->jsonData()['props'];
$this->assertSame('event', $props['stack'][0]['type']);
$this->assertArrayNotHasKey('contact_id', $props['stack'][0]['tabs'][0]['fields']);

// It is reachable from the sidebar.
$navigation = $props['navigation'];
$this->assertContains('Events', array_column($navigation, 'label'));
```

Assert the **presence of what you left on**, not only the absence of what you
turned off. A listing whose rows do not open passed seven green tests because
they only checked that edit and delete were gone.

---

## See also

- [graduating-a-resource.md](graduating-a-resource.md) — the ladder from a
  generated resource to a custom page, one rung at a time
- [panel-resources.md](panel-resources.md) — why resources are composed, not inherited
- [table-schema.md](table-schema.md) — columns, filters, groups, constraints
- [fields.md](fields.md) — what step 7's form can declare
