# @modufolio/panel

Schema-driven admin panel components for Inertia.js + Vue 3 — tables,
blueprint forms, filters, actions, drawers, widgets. Tables can be driven
entirely by a server-authored schema. The frontend companion
to [modufolio/appkit](https://github.com/modufolio/appkit), usable with any
Inertia-speaking backend.

> **Status: 0.x pre-release.** APIs may move between minors; the barrel
> export list (`src/index.ts`) is the public API surface.

## Install

```bash
npm install @modufolio/panel
```

Peer dependencies: `vue ^3.5`, `@inertiajs/vue3 ^3`, `@heroicons/vue ^2`,
and (optional) `@vueuse/core`, `vuedraggable`, `lodash`.

## Setup

One plugin call configures everything:

```js
import { createPanel } from '@modufolio/panel'

createApp({ render: () => h(App, props) })
  .use(createPanel({
    baseUrl: '/panel',                       // your backend mount path
    icons: { camera: CameraIcon },           // extend/override <Icon> names
    fields: {                                // app-specific blueprint fields
      'block-editor': () => import('./Fields/BlockEditorField.vue'),
    },
  }))
  .mount(el)
```

Every URL the panel builds goes through `panelUrl()` with that `baseUrl` —
nothing in the package hardcodes a mount path (CI-enforced).

## Styles (Tailwind CSS 4)

The package ships source `.vue` files and source CSS — your Tailwind build
compiles both. In your CSS entry:

```css
@import 'tailwindcss';
@import '@modufolio/panel/styles' layer(components);
@source "../node_modules/@modufolio/panel";
```

Theming: components use the semantic `--color-primary-*` / `success` /
`danger` / `warning` / `info` scales. Import
`@modufolio/panel/styles/tokens.css` for the defaults, then override any
value in your own `@theme` block to re-brand.

## Quick example

```vue
<script setup>
import { Table, BlueprintForm, Drawer, useResource, defineColumn, panelUrl } from '@modufolio/panel'

const resource = useResource({
  endpoint: panelUrl('/api/users'),
  columns: [
    defineColumn({ name: 'name', label: 'Name', sortable: true }),
    defineColumn({ name: 'email', label: 'Email' }),
  ],
})

const fields = [
  { type: 'text', key: 'name', label: 'Name', required: true, width: '1/2' },
  { type: 'text', key: 'email', label: 'Email', width: '1/2' },
  { type: 'toggle', key: 'active', label: 'Active', when: f => !!f.email },
]
</script>

<template>
  <Table :columns="resource.columns" :records="resource.records" />
  <BlueprintForm v-model="form" :fields="fields" label="Profile" />
</template>
```

## Server-driven tables

`SchemaTable` renders columns, filters, groups, summaries and a query builder
from a description the backend sends as a prop — no `columns` array in the
page:

```vue
<SchemaTable
  :schema="table"
  :records="organizations.data"
  :summaries="organizations.meta?.summaries ?? {}"
  :filter-values="form"
  @update:filter="setFilter"
/>
```

It wraps `Table`, so pages using `Table` directly are unaffected, and any
`#cell-{key}` or `#filters` slot you pass overrides the generated one.
See [docs/table-schema.md](docs/table-schema.md).

## Async writes

Overlapping writes are normal in an admin panel — a toggle in a row, a
debounced field, an optimistic star — and the failure modes are quiet. The
package ships the ordering primitives rather than leaving each page to
re-invent them:

```js
import { optimistic, writeKey, usePendingKeys } from '@modufolio/panel'

const { isPending, run } = usePendingKeys()      // which row is busy

const toggle = (row) => run(row.id, () => optimistic(
  () => { const was = row.active; row.active = !was; return () => { row.active = was } },
  () => apiFetch(url, { method: 'PATCH', body: { active: row.active } }),
  writeKey('rows', row.id, 'active'),            // orders overlapping writes
))
```

Also here: `useFieldSaver` (debounced saves with ordered status),
`useUnsavedChangesWarning` (prompt before a navigation discards edits,
logout included), `writeGate` (the ordering itself) and `moduleSingleton`
(state that survives a duplicated module copy).
See [docs/async-writes.md](docs/async-writes.md).

## Extension seams

- `registerFieldType(type, loader)` — add custom blueprint field types
  (rich-text editors, media pickers, ...) without forking the package.
  See [docs/custom-fields.md](docs/custom-fields.md).
- `registerIcons(map)` — add or override icon names used by `<Icon>`.
- `setPanelBaseUrl('/admin')` / `panelUrl(path)` — mount-path handling.

## Server contract

Works with any Inertia backend. A few small server-side conventions:

- **Overlays** — layer stack, scroll lock, positioning, keyboard:
  [docs/overlays.md](docs/overlays.md)
- **Drawers** — [docs/drawer-protocol.md](docs/drawer-protocol.md)
- **Table schema** — [docs/table-schema.md](docs/table-schema.md)
- **Relation fields** — lookup search/create endpoints, repeater rows and
  row-addressed errors: [docs/relation-fields.md](docs/relation-fields.md)

The reference implementation is appkit + appkit-portfolio.

## Tests

```bash
npm test            # inside ui/
```

Specs live in `tests/`, one file per module or component, run by the
package's own `vitest.config.ts` against `happy-dom`. Component specs mount
with `@vue/test-utils`; field components resolve asynchronously through the
registry, so wait for rendered output (`vi.waitFor`) rather than a fixed
number of ticks.

## License

MIT
