# Server-driven table schema

`SchemaTable` renders a table from a description the **server** sends as an
Inertia prop, instead of a `columns` array maintained in the page component.

It wraps `Table` rather than replacing it — pages using `Table` directly are
unaffected.

```vue
<SchemaTable
  :schema="table"
  :records="organizations.data"
  :summaries="organizations.meta?.summaries ?? {}"
  :filter-values="form"
  :query-params="computedParams"
  :sort-column="computedSortColumn"
  :sort-direction="computedSortDirection"
  @update:search="updateSearch"
  @sort="handleSort"
  @update:filter="setFilter"
/>
```

The reference backend implementation is appkit-portfolio's `App\Table\TableSchema`,
but the contract below is plain JSON — any backend can produce it.

---

## Props

| Prop | Type | Description |
|---|---|---|
| `schema` | `TableSchema` | Required. The server-authored description |
| `records` | `object[]` | Row data |
| `summaries` | `Record<string, Summary[]>` | Column aggregates, from `meta.summaries` |
| `filterValues` | `Record<string, unknown>` | Current filter form values |
| `cellHandlers` | `Record<string, fn>` | Save callbacks for `editable` columns |
| `queryParams` | `object` | Appended to record links so a drawer keeps list state |
| `rowActionHandlers` | `Record<string, fn>` | Services `behaviour: 'handler'` row actions, by name |
| `bulkActionHandlers` | `Record<string, fn>` | The same for bulk actions |
| `visibleColumns` | `string[] \| null` | Which columns to render. `null` shows them all — `hiddenByDefault` is only honoured when the page seeds this, see [Column visibility](#column-visibility) |
| `stack` | `StackItem[]` | The page's drawer stack. When given, the highlighted row follows the record the top drawer shows (arrow-key record pagination changes the stack without a click) |
| `drawerType` | `string` | Restricts stack-driven highlighting to top items of this drawer type. Omitted = match any top item by id (safe with UUIDs) |
| `search`, `sortColumn`, `sortDirection`, `loading`, `externalFocusedRowIndex` | | Forwarded to `Table`; an explicit `externalFocusedRowIndex ≥ 0` overrides the stack-derived row |

Events: `update:search`, `sort`, `rowClick`, `update:filter (key, value)`,
`resetFilters`.

---

## The schema contract

```ts
interface TableSchema {
  columns: SchemaColumn[]
  filters?: SchemaFilter[]
  groups?: Array<{ value: string; label: string }>
  constraints?: SchemaConstraint[]
  recordUrl?: string | null      // '/panel/organizations/{id}'
  emptyStateTitle?: string | null
  emptyStateDescription?: string | null
  searchable?: boolean
  bulkActions?: boolean
  stickyHeader?: boolean
  children?: SchemaChildTable[]  // nested tables under each row; expansion is derived from this
}

interface SchemaChildTable {
  key: string
  label: string
  relation: string
  source: string                 // key in the parent row holding the child rows
  columns: SchemaColumn[]        // never sortable: a child has no query
  recordUrl?: string | null      // '{parent}' is the parent row's id
  empty?: string | null
}
```

### Columns

```ts
interface SchemaColumn {
  key: string
  name: string
  label: string
  type: 'text' | 'select' | 'money' | 'numeric' | 'badge' | 'date'
      | 'boolean' | 'image' | 'icon' | 'color'
  sortable: boolean            // resolved server-side; never hand-declared
  valueKey?: string            // dot path, e.g. 'organization.name'
  descriptionKey?: string      // second line, read from another field
  linksToRecord?: boolean
  urlTemplate?: string         // dot-path placeholders: '{id}', '{organization.id}'
  showArrow?: boolean
  placeholder?: string         // default '—'
  toggleable?: boolean
  hiddenByDefault?: boolean
  weight?: 'medium' | 'bold'
  align?: 'left' | 'center' | 'right'
  color?: string
  icon?: string
  limit?: number
  copyable?: boolean
  size?: string                // type: 'image': 'sm' | 'md' | 'lg' | 'xl'
  rounded?: string             // type: 'image': 'none' | 'sm' | 'md' | 'lg' | 'full'
  currency?: string            // type: 'money'
  decimals?: number            // type: 'numeric'
  format?: string              // type: 'date'
  relative?: boolean
  colors?: Record<string, string>
  options?: Array<{ label: string; value: string; class?: string }>
  editable?: boolean
  disabledWhen?: string
  readOnlyWhen?: string
  summaries?: Array<{ type: string; label: string }>
}
```

Helpers exported alongside: `getPath`, `resolveRecordUrl`, `isEmptyValue`,
`cellClasses`, `truncate`, `formatValue`, `emptyFilterValue`, `filterDefaults`,
`visibleColumnDefaults`.

### Empty cells never link

A cell whose value is empty (`null`, `undefined`, `''`) renders its
`placeholder` — `'—'` by default — **unlinked**, whatever the column declares.
`linksToRecord` and `urlTemplate` are both ignored for that row.

This is deliberate. A `urlTemplate` such as
`/panel/contacts/{id}/organization/{organization.id}` has nothing to resolve
`{organization.id}` to when the contact has no organization, and a link
labelled `—` that lands somewhere arbitrary is worse than plain text. So a
contact *with* an organization drills into it and one *without* is inert —
including for `type: 'image'`, where an absent picture shows the placeholder
graphic and clicking it does nothing.

The consequence to design around: the row itself is not a link, so an empty
cell is dead space. Put the record's own link on a column that is always
present (the name or title), which every listing here does.

### Image columns

`type: 'image'` reads **one URL** from the row — `size` and `rounded` control
how it renders, and both belong in the schema rather than in a page override,
so two listings cannot drift apart:

```php
Column::make('cover_url')->label('Cover')->type('image')
    ->size('lg')->rounded('none')->linksToRecord()->notSortable(),
```

`rounded` defaults to `'full'` (an avatar); artwork and covers usually want
`'none'` or `'md'`. When the row holds a media *object* rather than a URL,
point `valueKey` at the field: `->value('cover.thumbnail_url')`. An empty value
renders the placeholder graphic, not a dash.

---

## Actions

`schema.actions` and `schema.bulkActionItems` are rendered by `SchemaTable`
itself — including the confirmation dialogs — when the page passes no
`#actions` / `#bulkActions` slot. A page slot always wins.

```ts
interface SchemaRowAction {
  name: string
  behaviour: 'drawer' | 'dialog' | 'visit' | 'delete' | 'handler'
  label: string
  icon?: string; color?: string
  urlTemplate?: string      // dot-path placeholders, resolved per row
  previewUrl?: string       // 'delete' only: what the deletion would cost
  hiddenWhen?: string; visibleWhen?: string
  confirm?: boolean; confirmMessage?: string
}

interface SchemaBulkAction {
  name: string
  behaviour: 'post' | 'handler'
  label: string
  icon?: string; color?: string; variant?: string
  url?: string              // POST { ids: [...] }
  confirm?: boolean
  confirmMessage?: string   // '{count}' is replaced with the selection size
}
```

| Behaviour | What the table does |
|---|---|
| `drawer` | Opens the row's `recordUrl` on the drawer stack (`visitDrawer`) |
| `dialog` | The same navigation, to the action's own `urlTemplate` — the frame is the server's answer, so the table never learns what the dialog does |
| `visit` | `router.visit(url)` |
| `delete` | Confirms — with the server's plan when `previewUrl` is set — then `router.delete(url)` |
| `post` | `router.post(url, { ids })` |
| `handler` | Calls `rowActionHandlers[name]` / `bulkActionHandlers[name]`; nothing happens if none is registered, because a schema can outlive the page that serviced it |

`drawer` and `dialog` are one code path: both are frames on the same stack, and
the item the server returns carries `presentation` to say which renders. That
is what keeps the behaviour list from growing per feature — a new interaction
is a new route, not a new case here. See the
[drawer protocol](./drawer-protocol.md#two-frames).

Two delete flows exist because `useDeleteConfirmation` decides at construction
whether it has a preview: an action with `previewUrl` asks the server what the
deletion would cost, one without degrades to a plain confirmation. Handing the
preview flow an empty URL would report every record as blocked.

```vue
<SchemaTable
  :schema="table"
  :row-action-handlers="{ restore: (record) => router.put(`/panel/x/${record.id}/restore`) }"
/>
```

---

## Column visibility

Columns marked `toggleable` appear in `ColumnToggle`'s dropdown; ones marked
`hiddenByDefault` start switched off. Neither flag does anything on its own —
`Table` filters against the list the page hands it, so the page owns the state:

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

`visibleColumnDefaults` is what honours `hiddenByDefault`; seeding the ref with
every key instead silently ignores the flag. Hiding a column changes nothing
about the query — it is a client-side concern, and is not reflected in the URL.
A column left un-`toggleable` cannot be hidden, which is how the identity
column (name, title) stays on screen.

---

## Slots

Everything you pass is forwarded to `Table`; anything you don't is generated.

| Slot | Behaviour |
|---|---|
| `cell-{key}` | **Overrides** the generated cell for that column |
| `filters` | **Replaces** the generated filter popover entirely |
| `expandedRow` | **Replaces** the generated child tables for a row (`{ record }`) |
| `headerActions`, `actions`, `bulkActions`, `pagination`, `header` | Forwarded as-is |

Overriding one column doesn't opt you out of the others — the component filters
its generated columns against `useSlots()`.

---

## Filters

When `schema.filters` is present, `SchemaTable` renders a `FilterPopover`
containing the right control per filter type (`select`, `multiSelect`,
`ternary`, `trashed`, `dateRange`), plus a group-by select and the query
builder when the schema declares them.

Values are **controlled** — the component never mutates `filterValues`. It
emits `update:filter (key, value)`; the page writes it into its own form state:

```js
const { form } = useListFilters('/organizations', props.filters, {
  defaults: filterDefaults(props.table),   // keys come from the schema
})

function setFilter(key, value) {
  form[key] = value
}
```

`filterDefaults(schema)` returns the key → empty-value map, including `group`
and `constraints` when those are declared, so the page never restates filter
keys.

---

## Editable cells

The schema declares *that* a column is editable and *what* it offers; a save
callback can't be JSON, so the page injects it:

```js
const cellHandlers = {
  role: (record, _column, value) => updateField(record, 'role', value),
  status: (record, _column, value) => updateField(record, 'account_status', value),
}
```

Keyed by **column key** — note `status` above persists to `account_status`,
which the schema's `valueKey` handles for display and the handler for the write.

An editable cell is never wrapped in a record link; the control would navigate
away on the first click.

---

## Summaries

Aggregates arrive per column and render in a `<tfoot>` row via `Table`'s
`summary` slot:

```json
{ "name": [{ "type": "count", "label": "Organizations", "value": 5 }] }
```

They belong with the data, not the schema — they change with every filter. The
server should compute them over the **filtered set**, not the current page.

---

## Grouping

`schema.groups` offers the user a grouping; the active key rides in
`filterValues.group`. The **server** orders rows by the group field so they
cluster, and `Table`'s `groupBy` prop draws a heading whenever the value
changes. The client does not re-sort.

---

## Query builder

`schema.constraints` drives `QueryBuilder.vue`: the user picks a field, an
operator from that field's type, and value(s). Each operator carries its
arity (`0`, `1` or `2`), so the UI knows how many inputs to draw. Conditions
are ANDed and sent as `constraints[]`.

The value input follows the constraint's `type`: a number spinner, a date
picker, a Yes/No select for a boolean (whose single `is` operator takes a
value, rather than the pair of valueless operators it used to have), and a text
box otherwise.

The server must treat both the field and the operator as allowlists — the
client is a convenience, not a guarantee.

---

## Why no closures

The schema is JSON, so it cannot carry callbacks. Anything per-row is either
computed server-side into a field the schema names (`descriptionKey`), or
handled by a `#cell-{key}` slot override.

## Editable text cells

`Column::make('phone')->editable()` on a `text` column renders an inline input:
Enter or blur saves through the page's handler for that column key, Escape
reverts, and a rejected save keeps the typed value on screen with the reason
attached rather than making the user retype it from memory.

The page supplies the *how*, as it already does for editable selects and
toggles — a save closure cannot cross the schema's JSON boundary.

## Custom column types

Columns have the same extension seam as blueprint fields:

```ts
import { registerColumnType } from '@modufolio/panel'
import SparklineColumn from './Columns/SparklineColumn.vue'

registerColumnType('sparkline', SparklineColumn)
```

A registered component receives `{ value, record, column, label, onUpdate }`
and owns its cell completely, including how it renders an empty value. Unlike
the field registry this resolves synchronously — a column renders once per
visible cell, and an async loader would leave holes in the grid on first paint.
Pass `defineAsyncComponent` yourself if you want that behaviour.

Registering a built-in type's name replaces it everywhere the schema asks for
it, which is how an application restyles `badge` without forking the table.

## Active filter indicators

`SchemaTable` renders a chip per active filter between the toolbar and the
rows: the filter's label, its value in words (the option's label, not the raw
query-string value), and an X that clears just that one. Filters live behind a
popover, so without this the only sign a result set has been narrowed is a
count badge on a closed button — and "no results" reads as "no such records".

Pass a `#filterIndicators` slot to replace the strip entirely.
