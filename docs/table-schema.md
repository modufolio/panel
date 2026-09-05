# Table schema

A table is declared once, in PHP, and shipped to Vue as an Inertia prop.
`SchemaTable.vue` renders it. The page no longer carries a `columns` array or
per-column cell markup.

```php
public function tableSchema(): ?TableSchema
{
    return TableSchema::make()
        ->recordUrl('/panel/organizations/{id}')
        ->emptyState('No organizations found', 'Get started by creating a new organization.')
        ->bulkActions()
        ->columns([
            Column::make('name')->linksToRecord()->descriptionKey('status_label'),
            Column::make('city')->linksToRecord(),
            Column::make('phone')->linksToRecord(),
        ]);
}
```

Return `null` (the default) to keep hand-written columns in the page component.

### Schema options

| Method | Effect |
|---|---|
| `columns(Column[])` | The columns, in render order |
| `recordUrl(string)` | Where a `linksToRecord()` cell goes; `{placeholders}` are dot paths against the row |
| `emptyState(string $title, ?string $description)` | Shown instead of an empty table |
| `actions(RowAction[])` | Row actions — see [Actions](#actions) |
| `bulkActions(bool\|BulkAction ...)` | Enable selection, and optionally declare what to do with one. `bulkActions()` alone renders the checkbox column and leaves the buttons to the page |
| `searchable(bool = true)` | Search box (on by default) |
| `stickyHeader(bool = true)` | Header stays put while the body scrolls (on by default) |
| `filters(Filter[])` | See [Filters](#filters) |
| `groups(Group[])` | See [Groups](#groups) |
| `constraints(Constraint[])` | See [Query builder constraints](#query-builder-constraints) |
| `children(ChildTable[])` | Related rows under each row, in a nested table — see [Child tables](#child-tables) |

---

## The closure boundary

This is the one design constraint everything else follows from: **the schema
crosses a JSON boundary, so it cannot contain closures.**

Filament — the obvious comparison — leans on them heavily
(`->tooltip(fn ($record) => …)`), which works because Livewire renders
server-side per row. We render in Vue, so a callback cannot survive
`json_encode`.

Four things closures are used for, and where each goes here:

| Purpose | Filament | Here |
|---|---|---|
| Derived cell value | `->state(fn ($r) => …)` | Compute it in the **presenter**, expose it as a field |
| Conditional display text | `->description(fn ($r) => …)` | Presenter field + `->descriptionKey('…')` |
| Query mutation | `->query(fn (Builder $q) => …)` | A **typed filter/constraint**, applied server-side by name |
| Anything else | closure | A `#cell-{key}` slot override on the page |

The escape hatch is real and cheap: a page-provided `#cell-{key}` slot **wins**
over the generated cell, and overriding one column doesn't opt you out of the
others.

---

## Columns

`Column::make($key)` — the key is the record field, the sort param, and the
override slot name.

### Value and linking

| Method | Effect |
|---|---|
| `label(string)` | Header text (defaults to a humanised key) |
| `value(string)` | Read from another field; **dot paths supported** (`organization.name`) |
| `linksToRecord()` | Link the cell to the row's record via the schema's `recordUrl` |
| `linksTo(string)` | Link somewhere else; `{placeholders}` are dot paths against the row |
| `arrow()` | Accented drill-down style with a trailing arrow |
| `descriptionKey(string)` | Second line, read from another field |
| `placeholder(string)` | Shown when the value is empty (default `—`) |

An empty cell renders its placeholder **unlinked** — a link labelled "—" is
worse than plain text. A URL whose placeholder can't be resolved yields no
link rather than a 404.

```php
Column::make('organization')
    ->value('organization.name')
    ->linksTo('/panel/contacts/{id}/organization/{organization.id}')
    ->arrow(),
```

### Formatting

| Method | Effect |
|---|---|
| `type(string)` | `text`, `badge`, `date`, `boolean`, `image`, `icon`, `color`, `select` |
| `size(string)` | Thumbnail size for `image` columns: `sm`, `md`, `lg`, `xl` |
| `rounded(string)` | Corners for `image` columns: `none`, `sm`, `md`, `lg`, `full` |
| `money(string $currency = 'EUR')` | Currency, formatted client-side via `Intl` |
| `numeric(int $decimals = 0)` | Fixed-precision number |
| `boolean()` | Tick / cross |
| `format(string, bool $relative = false)` | Date format, for `date` columns |
| `colors(array\|class-string)` | Value → colour map for badges; a backed enum implementing `HasColor` supplies its own |
| `weight(string)` | `medium` or `bold` |
| `align(string)` | `left`, `center` or `right` |
| `color(string)` | Text colour token (`primary`, `success`, `danger`, `warning`, `info`, `gray`) |
| `icon(string)` | Registered icon name, rendered before the value |
| `limit(int)` | Truncate for display, full value in the `title` attribute |
| `copyable()` | Click-to-copy affordance |

`limit` truncates for display and keeps the full value in the `title`
attribute.

#### Image columns

An `image` column reads **one URL** from the row. When the record carries a
media *object* instead — a presenter's `cover`, say — point `value()` at the
field rather than overriding the cell in the page: two listings rendering the
same kind of picture should not each hand-write their own size and corners.

```php
Column::make('cover')->label('Cover')->type('image')
    ->value('cover.thumbnail_url')
    ->size('lg')->rounded('none')
    ->linksToRecord()
    ->notSortable()->toggleable(),
```

`rounded` defaults to `full` on the client, which suits an avatar and not a
poster — state it for artwork. A row with no picture renders the placeholder
graphic (not a dash), and stays unlinked like any other empty cell. `size` and
`rounded` are ignored by every other column type.

### Visibility and sorting

| Method | Effect |
|---|---|
| `toggleable(bool $hiddenByDefault = false)` | User can show/hide the column, via the page's `ColumnToggle` |
| `notSortable()` | Suppress sorting the query would otherwise allow |
| `width(string)` | Fixed column width |
| `summarize(Summary\|Summary[])` | Footer aggregate(s) — see [Summaries](#summaries) |

`toggleable()` only *offers* the column in the dropdown; which columns are on
is client state the page owns, so a listing wanting the control renders
`ColumnToggle` and seeds it from the schema:

```vue
<SchemaTable :schema="table" :visible-columns="visibleColumns" …>
  <template #headerActions>
    <ColumnToggle v-model="visibleColumns" :columns="table.columns" />
  </template>
</SchemaTable>
```

```ts
const visibleColumns = ref<string[]>(visibleColumnDefaults(props.table))
```

`visibleColumnDefaults()` is what honours `toggleable(hiddenByDefault: true)`;
seeding the ref by hand silently ignores it. A column left un-`toggleable`
cannot be hidden, which is how the identity column (name, title) stays on
screen. Hiding is display-only — it changes no query and is not in the URL.

### Inline editing

| Method | Effect |
|---|---|
| `editable(bool = true)` | Render an in-place control; the page supplies the handler |
| `options(array\|class-string)` | Choices — a literal list or a backed enum class |
| `disabledWhen(string)` | Control stays visible but inert when that field is truthy |
| `readOnlyWhen(string)` | Control is replaced by a static badge when that field is truthy |

```php
Column::make('status')
    ->value('account_status')
    ->type('select')->editable()
    ->options(AccountStatus::class)
    ->disabledWhen('deleted_at'),
```

The schema declares **that** a column is editable and **what** it offers; the
page supplies **how** to persist it, because a save callback can't be
serialised:

```js
const cellHandlers = {
  status: (record, _column, value) => updateField(record, 'account_status', value),
}
```

`disabledWhen('field')` keeps the control visible but inert when that field is
truthy; `readOnlyWhen('field')` drops it for a static badge.

### Sortability is derived

**A column cannot declare itself sortable.** There is no `sortable()` — only
`notSortable()`. Sortability is resolved at serialisation time from the
resource's list query:

```php
$column->wantsSorting() && $listQueryClass::mapSortField($column->key()) !== null
```

So "city is sortable" lives in exactly one place. Before this, it lived in the
query's allowlist *and* in a hand-written `columns` array — and the two had
already drifted (the query allowed ordering by `phone` while the column said
`sortable: false`).

---

## Actions

Row and bulk actions are declared, not written into every listing's slots.
Before this, View/Edit/Delete was ~20 lines of `ActionGroup` markup per page
and the bulk equivalent was a hand-written `confirm()` — three copies of the
same paragraph across the panel.

```php
->actions([
    RowAction::view(),
    RowAction::edit('/panel/movies/{id}/edit'),
    RowAction::delete('/panel/movies/{id}')
        ->previewUrl('/panel/movies/{id}/delete-preview'),
    RowAction::dialog('rate', '/panel/movies/{id}/rate')->label('Rate…'),
    RowAction::make('restore')->icon('check-circle')->visibleWhen('deleted_at'),
])
->bulkActions(BulkAction::delete('/panel/movies/bulk-delete'))
```

| Constructor | Behaviour |
|---|---|
| `RowAction::view()` | Opens the row's drawer, via the schema's `recordUrl` |
| `RowAction::edit($url)` | `router.visit($url)` |
| `RowAction::delete($url)` | Confirm, then `DELETE $url` |
| `RowAction::dialog($name, $url)` | Opens `$url` as a dialog on the drawer stack |
| `RowAction::make($name)` | Dispatched to the page's `rowActionHandlers[$name]` |
| `BulkAction::delete($url)` | Confirm, then `POST $url` with `{ids: [...]}` |
| `BulkAction::post($name, $url)` | The same without the confirmation |
| `BulkAction::make($name)` | Dispatched to `bulkActionHandlers[$name]` |

Shared modifiers: `label()`, `icon()`, `color()`, `confirm($message?)`.

Row actions add three more:

| Modifier | Effect |
|---|---|
| `hiddenWhen($field)` / `visibleWhen($field)` | Per-row visibility, by naming a boolean on the record — a closure cannot cross the boundary |
| `previewUrl($url)` | Turns a blind "are you sure?" into the server's account of what would go with the record |
| `soft()` | The record goes to the trash, so the dialog must not say "cannot be undone" |

### A pointer beats a verb

The first four constructors name a *behaviour* — something `SchemaTable` knows
how to perform. That works for the handful every listing needs, and stops
working immediately after: the next interaction (publish, duplicate, move,
assign) is either a new case in the client or a page-side handler plus a
hand-written dialog.

`RowAction::dialog()` names a **URL** instead. The route on the other end
returns a frame declaring `presentation: 'dialog'`, and the table just
navigates — the same visit a drawer action makes, because it *is* the same
stack. The client never learns what the dialog does.

```php
RowAction::dialog('publish', '/panel/posts/{id}/publish')->label('Publish…')
```

```php
// PostController — a dialog route is an ordinary route that renders the
// listing underneath with one extra frame on top.
DrawerStack::create()->push(
    type: 'publish',
    data: [...],
    title: 'Publish post',
    href: $this->urlGenerator->generate('posts_publish_dialog', [...]),
    width: 'sm',
    presentation: DrawerStack::AS_DIALOG,
);
```

The page renders the body through the same per-type slot a drawer uses
(`#publish`, `#footer-publish`). Because the dialog is a stack item it is
addressable, deep-linkable and closed by navigating away. See the
[drawer protocol](../ui/docs/drawer-protocol.md#two-frames).

New interactions should reach for this before `make()`: a handler is right
only when there is no server route behind the action at all — opening a tab,
copying to the clipboard.

**The table owns the dialogs.** An action saying `confirm` does not require the
page to remember to render one; `SchemaTable` holds both the delete
confirmation and the bulk one.

**A page slot still wins.** `#actions` or `#bulkActions` on the page replaces
the generated menu entirely, so adopting declared actions is additive for
listings that already hand-write theirs.

### Generated resources get them for nothing

`ResourceListing` fills in the standard trio when a resource declares no
actions of its own, derived from **which routes exist** and gated by
`canEdit()` / `canDelete()` for the viewer asking:

```php
// PostResource declares no actions — these arrive anyway:
'actions' => [view → drawer, edit → /panel/posts/{id}/edit, delete → …],
'bulkActionItems' => [delete → /panel/posts/bulk-delete],
```

A resource that *does* declare actions keeps them, minus any `edit`, `delete`
or `restore` this viewer may not perform (`restore` rides the delete
permission — both govern the same trash lifecycle). Gating in the schema
rather than in the page is the point: a listing cannot offer what the server
would refuse.

**Route existence only gates the defaults.** A declared action names its own
URL, so the route behind it is the resource's business — checking for a
`{key}_destroy` route there would drop Delete from every listing whose
controller named it something else (posts call it `posts_delete`). For
declared actions, permission is the only question asked.

URL templates come from the router (generated with a sentinel uuid, then
substituted), never string-built — `users_export` lives at `/panel/export/users`,
so a hand-built path was already wrong once.

---

## Filters

```php
->filters([
    Filter::select('status')->options(ContactStatus::class),
    Filter::select('organization')->relationship(Organization::class, 'name', 'uuid'),
    Filter::ternary('is_company', 'isCompany')->trueOption('Companies')->falseOption('People'),
    Filter::dateRange('created', 'createdAt')->label('Created'),
    Filter::trashed(),
])
```

| Constructor | Predicate |
|---|---|
| `Filter::select($key, $field?)` | `field = value` |
| `Filter::multiSelect($key, $field?)` | `field IN (values)` |
| `Filter::ternary($key, $field?)` | Boolean compare against `trueValue` |
| `Filter::dateRange($key, $field?)` | `>= from` and `< until + 1 day` |
| `Filter::trashed()` | None — the list query owns it |

Options come from a literal list, a backed enum class, or
`->relationship(Entity::class, 'label', 'value')`, which resolves against the
database at serialisation time so the client still receives plain data.

`->handledByQuery()` renders the control but leaves the predicate to the list
query — for filters it already owns.

**Safety:** the entity field is baked in at construction, never read from the
request. Only declared keys are consulted, so an arbitrary query param cannot
reach the query builder.

**The `until` bound is inclusive of the whole end day.** An off-by-one there
silently drops everything created today.

Frontend wiring: `filterDefaults(props.table)` builds the key/empty-value map
`useListFilters` needs, so the page never restates filter keys.

---

### Defaults

A filter may carry the value that applies when the request names none:

```php
Filter::select('status')->options(...)->default('open')
```

The default travels to the client, which shows it in the control **without**
counting it as an active filter or a chip, and returns to it on reset. The
`trashed` control gets its default from the resource's `defaultTrashed()`
automatically — a resource listing deleted rows by default shows "With
Deleted" selected, and a reset leaves it there rather than blanking it for
the server to refill on the next request.

---

## Groups

```php
->groups([Group::make('city'), Group::make('country')])
```

The server orders by the group field so rows cluster; the client draws a
heading whenever the value changes. Grouping that doesn't cluster is just a
sort, so there's a test asserting contiguity. An unknown group key is ignored.

---

## Summaries

```php
Column::make('name')->summarize(Summary::count('Organizations')),
Column::make('hours')->numeric(1)->summarize([Summary::sum(), Summary::average()]),
```

`Summary::sum() / average() / count() / min() / max()`.

Computed in **one aggregate query over the filtered set** — not the current
page, which is the only reason they're worth a round trip. They ride in
`meta.summaries`, not the schema, because they change with every filter.

Summarising a dot-path column throws a `LogicException`: aggregating across a
relation needs an explicit join.

---

## Child tables

Related rows shown under a parent row: a movie's cast under the movie, an
order's lines under the order. The read half of master–detail; the drawer's
relation tabs are the write half.

```php
TableSchema::make()
    ->columns([...])
    ->children([
        ChildTable::relation('cast', 'Cast')
            ->columns([
                Column::make('actor')->linksTo('/panel/actors/{actor_id}'),
                Column::make('character'),
            ])
            ->empty('No cast listed yet.'),
    ]);
```

A child names a **to-many association** of the listed entity and the columns
to show per related row. The rows themselves come from the presented parent
row, under `source` — the snake_cased relation by default, or whatever key
`->source()` names — exactly as `DrawerTab::relation()` reads them. So the
presenter's list row has to carry them, the way its detail array already does
for the drawer.

Two things the listing does with the declaration:

- **Validates the relation against Doctrine's metadata** when it renders. A
  name the entity does not map, a to-one, or a many-to-many is refused with a
  `LogicException` naming the class — not a blank nested table in the browser.
- **Loads the association for the page in one query.** The list query sets an
  eager fetch mode for every declared child, which Doctrine turns into one
  `IN` query per page. This is why children are one-to-many only for now: a
  many-to-many would load once per row, and a bound the panel imposes must be
  visible rather than paid for quietly. It is also why the association is not
  joined in the list query's `applyEagerLoads()` — a fetch-join under the
  page's LIMIT counts child rows against it and shortens pages.

What a child cannot declare, and why: filters, groups, constraints, summaries,
search, bulk actions and inline editing all need a query of their own, and a
child has none. Its columns therefore serialise as never sortable, and a
column with a summary or `editable()` is refused at declaration.

`recordUrl()` links each child row. Placeholders resolve against the child row,
plus `{parent}` for the parent row's id — the drawer-tab convention:

```php
ChildTable::relation('cast', 'Cast')->recordUrl('/panel/movies/{parent}/cast/{id}')
```

On the client, `SchemaTable` turns a row expandable as soon as the schema
declares children and renders one nested table per child in the expanded
row. A page that writes its own `#expandedRow` slot still wins.

---

## Query builder constraints

The user composes ad-hoc conditions. Entirely declarative — a constraint
declares only `{key, field, type, label}`, and the **operators come from the
type**:

```php
->constraints([
    Constraint::text('name'),
    Constraint::number('price'),
    Constraint::boolean('is_visible'),
    Constraint::date('created', 'createdAt')->label('Created'),
])
```

Each kind names a **field type**, and both the operator menu and the predicate
come from there — `Constraint::text()` is `TextType`, `number()` is
`NumberType`, `boolean()` is `ToggleType`, `date()` is `DateType`. So a
listing's ad-hoc conditions, a field's declared filters and
modufolio/json-api's filters all speak one vocabulary instead of three:

| Type | Operators |
|---|---|
| `text` | `contains`, `not_contains`, `equals`, `not_equals`, `starts_with`, `ends_with`, `empty`, `not_empty` |
| `number` | `equals`, `not_equals`, `gt`, `gte`, `lt`, `lte`, `between`, `empty`, `not_empty` |
| `boolean` | `is` |
| `date` | `on`, `after`, `before`, `between`, `empty`, `not_empty` |

The keys deliberately match modufolio/json-api's filter strategies. `on` is a
half-open day range rather than an equality, so the same declaration works
against a `date` and a `datetime` column — equality on the latter matches only
the stroke of midnight.

Arity is a property of the vocabulary rather than of any one type: `between`
takes two values, `empty` and `not_empty` take none, everything else takes one.
It ships with each operator so the UI knows how many inputs to draw. Conditions
are ANDed.

> **Renaming, for anyone with saved URLs.** These operators were once
> `notContains` / `isEmpty` / `isTrue` / `isOn`, from a table `Constraint` kept
> of its own. A condition using an old name is not declared any more, and is
> dropped.

**Both halves are allowlisted**: the field comes from the declared constraints,
the operator from its type. A condition naming an undeclared field, or an
operator from another type, is dropped rather than applied — that is the only
thing standing between a query param and DQL.

---

## Frontend

```vue
<SchemaTable
  :schema="table"
  :records="organizations.data"
  :summaries="organizations.meta?.summaries ?? {}"
  :filter-values="form"
  :query-params="computedParams"
  :cell-handlers="cellHandlers"
  :stack="stack"
  drawer-type="organization"
  @update:search="updateSearch"
  @sort="handleSort"
  @update:filter="setFilter"
>
  <template #actions="{ record }">…</template>
  <template #pagination>…</template>
</SchemaTable>
```

Rows expand into nested tables when the schema declares
[children](#child-tables); a page-supplied `#expandedRow` replaces them.

`SchemaTable` wraps `Table` rather than replacing it — every page still using
`Table` directly is unaffected. It forwards any slot you pass (`headerActions`,
`filters`, `actions`, `bulkActions`, `pagination`) and generates the rest.

A page-supplied `#filters` slot replaces the generated filter popover
entirely, the same way `#cell-{key}` replaces a generated cell.

Passing the drawer `stack` (with an optional `drawer-type`) keeps the
highlighted row in sync with the record the top drawer is showing —
arrow-key record pagination changes the stack without a click, so the
highlight must be derived from it, not from the row that opened the drawer.
Every listing page gets this for free by forwarding its `stack` prop.

---

## Enum contracts

The highest-leverage way to avoid per-column configuration. An enum that knows
its own presentation configures a badge, a filter and a form control at once:

```php
enum AccountStatus: string implements HasLabel, HasCssClass
{
    use ProvidesOptions;

    public function getLabel(): string    { … }
    public function getCssClass(): string { … }
}
```

`ProvidesOptions` supplies `toOptions()` once — it replaced five verbatim
copies — emitting `value`, `label`, and `color`/`class` only when the enum
declares the matching contract. `Column::options()`, `Column::colors()` and
`Filter::options()` all accept the enum class directly, so a status column and
its filter are declared without restating a single label or colour:

```php
enum PostStatus: string implements HasLabel, HasColor { … }

->filters([
    Filter::select('status')->options(PostStatus::class),
])
->columns([
    Column::make('status')->label('Status')
        ->type('select')->options(PostStatus::class)->colors(PostStatus::class),
])
```

A `select` column that is not `editable()` renders as a badge — label from the
options, colour from the map — which is why a read-only status column names
the enum twice rather than using `type('badge')`, whose label is the raw
stored value.

> Two colour vocabularies exist today: raw hues (`green`, `blue`) consumed by
> `IssuePresenter`/`ProjectPresenter`, and the panel's semantic tokens
> (`success`, `danger`, …) that `BadgeColumn` accepts. `HasColor` does not
> unify them.

---

## See also

- [panel-resources.md](panel-resources.md) — where a schema is declared
- [panel-resources.md#exports](panel-resources.md#exports) — what the Export
  button actually sends, and why the schema is only its fallback
- [ui/docs/table-schema.md](../ui/docs/table-schema.md) — the client half:
  `SchemaTable`, cell overrides, the query builder
