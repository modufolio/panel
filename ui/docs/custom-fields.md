# Custom field types

Blueprint forms resolve field components through a registry. Built-ins cover
the standard inputs (`text`, `textarea`, `select`, `multiselect`, `toggle`,
`checkbox`, `range`, `date`, `datetime`, `time`, `date-range`, `file`, `color`,
`tags`, `belongs-to`, `repeater`, `toggle-buttons`, `set`, `embed`, `data`,
`hidden`). Anything app-specific is registered at boot:

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

### Render it inside `FieldPrimitive`

Do not hand-roll the label, the help text or the error message. `FieldPrimitive`
is the frame every built-in field renders inside — it owns the grid width, the
label, the description, the message, and the ARIA relationships between them —
and it hands the control what it needs through slot props:

```vue
<template>
  <FieldPrimitive
    v-bind="{ width, id, label, help, error, required }"
    wrapper-class="ui-field-signature"
    v-slot="{ describedBy, invalid }"
  >
    <canvas :id="id" :aria-describedby="describedBy" :aria-invalid="invalid" />
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { FieldPrimitive, fieldWidthProp } from '@modufolio/panel'

const props = defineProps({ ...fieldWidthProp, /* … */ })
</script>
```

| Slot prop | Use |
|---|---|
| `describedBy` | `aria-describedby` — the help and error ids, or `undefined` |
| `invalid` | `aria-invalid` — `true` while there is an error, else `undefined` |
| `descriptionId` / `errorId` | The individual ids, when the control wires them itself |

Pass the same `id` to the frame and to the control: the help and error ids
derive from it, and that is what connects the three.

A field wrapping **several** controls passes `as="fieldset"`, which renders the
caption as a `<legend>` — `<label for>` may only point at one control, so a
group's label would otherwise label nothing.

A layout the stacked frame cannot express — a checkbox whose label sits beside
the box — composes the parts instead:

```vue
import { FieldLabel, FieldDescription, FieldMessage } from '@modufolio/panel'
```

That split is what the built-ins do, and it exists because twenty-two of them
used to render this scaffolding themselves: three different spacings for the
gap under a label, `role="alert"` on some error paragraphs and not others, and
`aria-describedby` missing from most of them, so their help text was visible to
sighted users only.

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
