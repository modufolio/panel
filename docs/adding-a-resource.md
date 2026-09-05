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
| 8 | Register the resource | `config/services.php` and `config/panel_resources.php` |
| 9 | Menu item | `config/areas/{key}.php` |

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
            ->recordUrl('/panel/events/{id}')          // ← 1. where a row points
            ->emptyState('No events yet', '…')
            ->filters([...])
            ->columns([
                Column::make('title')->linksToRecord() // ← 2. which cell is the link
                    ->weight('medium'),
                Column::make('when')->linksToRecord()->label('When'),
            ]);
    }
```

**Both lines are required for a clickable row.** `recordUrl` says where a row
goes; `linksToRecord()` opts a *cell* into being the link. Neither implies the
other, and missing either leaves rows that look right and do nothing.

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
`formFieldKeys()` or `formFields()` returns non-null**:

```php
// Writable: field keys, with any layout or rules the schema cannot infer.
public function formFieldKeys(): array
{
    return ['name' => ['width' => '1/2'], 'birth_year' => ['width' => '1/2']];
}
```

So a read-only resource is one that simply declares no form fields. Overriding
`canCreate()`/`canEdit()`/`canDelete()` to `false` on top of that is belt-and-
braces: it states the intent, and keeps deletes off if someone later adds form
fields to make the listing editable.

Everything the form can declare — conditions, defaults, validation messages,
per-field access, and the field types a guessed form cannot reach — is in
[fields.md](fields.md).

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
$panel->resource(EventResource::class)
    ->roles(['ROLE_USER']);
```

The roles land on the route as `_is_granted_roles`, enforced by the kernel
before `ResourceController` runs — no guard clause needed.

---

## 9. Give it a menu item

`config/areas/{key}.php`. **Nothing errors if you skip this** — the route works,
and nothing in the panel links to it:

```php
return [
    'label' => 'Events',
    'link'  => ['name' => 'events'],   // the route PanelResourceRouteLoader
    'icon'  => 'calendar',             //   generated from key()
    'group' => 'Main',
    'order' => 16,
];
```

Icon names come from the panel's built-in set (`ui/src/Components/Core/Icon.vue`
in this package) or anything the app registered via `registerIcons()`.

---

## Silent failures

Every one of these was hit while building `EventResource`. None of them throws.

| Symptom | Cause |
|---------|-------|
| Rows render but clicking does nothing; Actions ▸ View is present | No `->recordUrl(...)` on the schema |
| `recordUrl` is set, still nothing clickable | No column has `->linksToRecord()` |
| Drawer shows `Contact Id`, `Has Passed` as if they were fields | No `drawerTabs()` — the details grid prints every presenter key |
| Route works, nothing in the panel links to it | Missing `config/areas/{key}.php` |
| The area file exists, an admin still sees no menu item | Its `roles` are intersected literally, with no role hierarchy — a `ROLE_SUPER_ADMIN` does not match an area gated on `ROLE_ADMIN`, though the *route* admits them. Name both |
| A `when` or `access` declaration that guards nothing | The write path bypasses `SubmissionHandler` and never calls `stripHidden()` / `stripDenied()` itself — see [fields.md](fields.md#wiring-the-guards) |
| Listing fine, drawer errors | Entity has no `uuid` |
| A create button that opens an empty form | `formFieldKeys()` returns keys the presenter/entity does not carry |
| Blueprint form throws `Unknown field type "x"` | A PHP `FieldType` emits a type string with no component registered in `ui/src/Components/Fields/fieldRegistry.ts` |
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
