# Changelog

## 0.1.0 (unreleased)

Initial extraction from appkit-portfolio.

- Core primitives: Button, Badge, Tag, Icon (+ registry), Label, Modal,
  Dropdown, Pagination, LoadingButton, FlashMessages, TextInput, SelectInput,
  Empty, ErrorBoundary
- Table system: Table, TablePagination, ColumnToggle, ExportButton,
  useTableExport, 10 column components, 7 filter components
- Blueprint forms: BlueprintForm, useBlueprint with field-type registry,
  FieldGrid, 13 built-in field components
- Drawer overlay navigation: Drawer, DrawerStack, DrawerLink,
  NestedDrawerForm + composables (server protocol: docs/drawer-protocol.md)
- Sections, Actions, Widgets, Wizard, Dialog/ConfirmDialog, Toast
- Resource composables: useResource, useInlineEdit, useRelationship,
  ResourceView
- Utils: apiFetch, optimistic, reconcile, url (panelUrl/setPanelBaseUrl),
  csrf token holder
- `createPanel()` plugin: baseUrl + icon registry + field registry in one call
- Tailwind 4 source styles + design tokens (styles/tokens.css)

### Overlays

A shared layer stack replaces the per-component `document` listeners every
overlay used to carry, and fixes the bugs that came with them.

- `useDismissableLayer()`: one ordered stack of open overlays. Escape and
  outside presses reach only the top of it — previously each of eleven
  components listened on `document` independently, so one Escape closed a
  dialog *and* the drawer behind it. The layer decides what to do with a
  dismissal, so a non-closable dialog can ignore Escape without the press
  falling through to what is underneath.
- `useBodyScrollLock()`: reference counted across every overlay, so closing a
  dialog opened from inside a drawer no longer unlocks a page the drawer is
  still covering. Compensates scrollbar width, so the page behind does not
  jump sideways.
- `hideOthers()`: takes the page behind a modal out of the accessibility tree
  using `inert` rather than `aria-hidden` — the browser refuses the latter
  over the focused element ("Blocked aria-hidden … descendant retained
  focus") while `inert` moves focus out on its own. Only the highest modal
  layer applies, and other registered layers are exempt, so stacked overlays
  cannot hide each other and leave nothing announced. Live regions keep
  announcing.
- `useFocusTrap()` recaptures focus on `focusin`, not just on Tab — but only
  while its overlay owns the screen, and only against focus that escaped to
  the part of the page the stack actually hid. Without both conditions a
  drawer's trap reaches into any panel opened above it, which showed up as a
  combobox closing the instant it opened.
- `useAnchoredPosition()` over `@floating-ui/vue` replaces three hand-rolled
  `getBoundingClientRect()` positioners and the deprecated Popper dependency.
- `useId()` wraps Vue's own, which is stable across a server render and its
  hydration; `Math.random()` ids are gone from fourteen sites.
- Overlays teleport to a configurable target — `createPanel({ teleportTarget })`
  — instead of a hard-coded selector the host had to know to provide.
- Docs: [overlays.md](docs/overlays.md), including why an overlay the host
  application renders itself must register as a layer too.

### Table

- `registerColumnType()`: columns gain the extension seam fields already had.
  Resolves synchronously, since a column renders once per visible cell and an
  async loader would leave holes in the grid; registering a built-in name
  replaces it.
- `TextInputColumn`: inline text editing, alongside the existing editable
  select and toggle. A rejected save keeps the typed value on screen with the
  reason attached — retyping a rejected edit from memory is worse than seeing
  it marked unsaved.
- `FilterIndicators`: a chip per active filter between the toolbar and the
  rows, naming the filter and its value *in words* rather than the raw query
  value, each with its own dismissal. Filters live behind a popover, so
  without this the only sign a result set has been narrowed is a count badge
  on a closed button — and "no results" reads as "no such records".

### Field types

- `datetime` and `time` field types. `TimePickerField` wraps the native
  control, which already provides keyboard entry, the viewer's own 12/24-hour
  convention and the platform picker on a phone; `DateTimePickerField`
  composes it with the existing calendar, because a date and a time are read
  and corrected separately.
- `tags` is registered. `TagsField` had shipped and been exported since the
  beginning while nothing registered its type, so any blueprint using
  `App\Panel\Field\TagsType` threw `Unknown field type "tags"` at render. A
  test now walks every type the server can emit and asserts it resolves.
- `NestedDrawerForm` accepts `date`, `time`, `datetime` and `checkbox`.

### Fixed

- **An editable column called its save handler twice.** Vue treats a prop
  named `onUpdate` as a listener for a declared `update` emit, so calling the
  prop *and* emitting invoked the page's handler a second time with the event
  object where `(record, column, value)` was expected. `useInlineEdit`'s
  `!record.id` guard degraded it to a console error and an unhandled
  rejection rather than a bogus request, but a less defensive handler would
  have made one. `SelectColumn`, `ToggleColumn` and `TextInputColumn` now emit
  only when no handler prop was supplied.
- **Saving a nested drawer form prompted about unsaved changes.** `submit()`
  sets `saving`, awaits the handler — which starts the reload navigation —
  and only closes the form afterwards, so the guard saw a visible, dirty form
  and asked whether to discard the edits that navigation was saving.
  `isDirty` is false while a submit is in flight; a failed save clears
  `saving`, leaves the form open, and the guard returns.
- The library builds to a single file. Field components are loaded through
  `import()` to break a cycle and to give `registerFieldType()` one loader
  shape, not for code splitting — and since `index.ts` also exports them,
  Rollup emitted thirteen ~130-byte chunks that only re-exported from
  `index.js`.

### Async write ordering

- `writeGate` + `writeKey` + `LOCAL_WRITE`: dispatch-ordered staleness
  control, so a slow write cannot overwrite a newer one. `seal()` stops an
  in-flight response re-creating a deleted record.
- `optimistic()` takes an optional key and skips a stale rollback — two
  quick toggles no longer let the first one's failure erase the second.
- `useFieldSaver` orders its status: a late stale save can neither repaint a
  newer success as an error nor hide a newer failure.
- `usePendingKeys()`: keyed in-flight tracking for per-row spinners, with
  re-entry guarding (the double-click case).
- `useUnsavedChangesWarning()` accepts a getter as well as an Inertia form,
  read at event time.
- `moduleSingleton()`: process-wide state that survives a bundler
  instantiating the module twice.

### Fields

- `belongs-to`: reverts to the held value's label when a dropdown is
  dismissed without a selection; `allowCreate` offers "Create …" for an
  unmatched name, POSTing to the same relation endpoint.
- `BlueprintForm` fans row-addressed server errors (`lines.1.name`) out to
  container fields as `nestedErrors`; a fresh error batch resets touched
  state so the server's answer about a just-edited field is not suppressed.
- Field registry extracted to `fieldRegistry.ts` so field components can
  resolve sub-components without importing the composable back.
- Docs: [async-writes.md](docs/async-writes.md),
  [relation-fields.md](docs/relation-fields.md).
