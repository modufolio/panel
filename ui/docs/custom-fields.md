# Custom field types

Blueprint forms resolve field components through a registry. Built-ins cover
the standard inputs (`text`, `textarea`, `select`, `multiselect`, `toggle`,
`checkbox`, `date`, `date-range`, `file`, `color`, `belongs-to`, `repeater`,
`toggle-buttons`). Anything app-specific is registered at boot:

```js
createPanel({
  fields: {
    'writer':       () => import('@/Fields/WriterField.vue'),
    'block-editor': () => import('@/Fields/BlockEditorField.vue'),
  },
})
```

or imperatively:

```js
import { registerFieldType } from '@modufolio/panel'
registerFieldType('signature', () => import('./SignatureField.vue'))
```

A field component that needs the resolver itself (a container type
rendering its own sub-fields) should import it from `fieldRegistry`, not
from `useBlueprint` — the composable imports the registry too, and going
back the other way re-creates a cycle between a composable and a component.

Then use the type in any blueprint:

```js
const fields = [
  { type: 'signature', key: 'signature', label: 'Sign here' },
]
```

## Field component contract

A field component receives (via `useBlueprint`'s `fieldProps`):

| Prop | Type | Notes |
|---|---|---|
| `modelValue` | any | v-model value for the field's key |
| `label` | string | |
| `width` | string | grid width (`full`, `1/2`, ...) — use `useFieldWidth` |
| `required` | boolean | |
| `error` | string | validation message ('' when valid) |
| `help`, `placeholder` | string? | only when set in the FieldDef |
| `options` | OptionItem[]? | for choice-style fields |

plus anything passed through the FieldDef's `props` object. Emit
`update:modelValue` with the new value.

### Container fields

A field holding a list of rows (a repeater, a matrix) also receives:

| Prop | Type | Notes |
|---|---|---|
| `fields` | FieldDef[] | the child's own field declarations |
| `nestedErrors` | Record&lt;string, string&gt; | row-scoped messages keyed `{index}.{subKey}`, the container's own prefix already stripped |

Render sub-fields through `resolveFieldComponent` so custom types work
inside containers too. See [relation-fields.md](relation-fields.md) for the
server side of that contract.

Registering an existing name (e.g. `'file'`) **overrides** the built-in —
that's the supported way to swap in, say, a resumable-upload field.

Unknown types throw at resolve time with the list of known types, so typos
fail loudly rather than rendering nothing.
