import type { Component } from 'vue'
import builtInTypes from './fieldTypes.json'

/**
 * The field-type → component registry, in a module of its own.
 *
 * Extracted from useBlueprint.ts so the arrows point one way: the registry
 * lazily imports RepeaterField, and RepeaterField needs
 * `resolveFieldComponent` for its sub-fields — while both lived in
 * useBlueprint, the two files imported each other. The cycle was tolerable
 * at runtime (one edge dynamic, one type-only), but a composable and a
 * component depending on each other is the kind of knot that only ever
 * tightens; now field components import this module, never the composable.
 */

type FieldLoader = () => Promise<Component | { default: Component }>

const fieldRegistry: Record<string, FieldLoader> = {
  'text':           () => import('./TextField.vue'),
  'textarea':       () => import('./TextareaField.vue'),
  'select':         () => import('./SelectField.vue'),
  'multiselect':    () => import('./MultiSelectField.vue'),
  'toggle':         () => import('./ToggleField.vue'),
  'checkbox':       () => import('./CheckboxField.vue'),
  'range':          () => import('./RangeField.vue'),
  'date':           () => import('./DatePickerField.vue'),
  'datetime':       () => import('./DateTimePickerField.vue'),
  'time':           () => import('./TimePickerField.vue'),
  'date-range':     () => import('./DateRangePickerField.vue'),
  'file':           () => import('./FileUploadField.vue'),
  'color':          () => import('./ColorPickerField.vue'),
  'belongs-to':     () => import('./BelongsToSelect.vue'),
  'repeater':       () => import('./RepeaterField.vue'),
  'tags':           () => import('./TagsField.vue'),
  'toggle-buttons': () => import('./ToggleButtonsField.vue'),
  'hidden':         () => import('./HiddenField.vue'),
  'data':           () => import('./DataField.vue'),
  'separator':      () => import('./SeparatorField.vue'),
  'embed':          () => import('./EmbedField.vue'),
  'set':            () => import('./SetField.vue'),
}

// Cache resolved components so each type is only imported once
const resolvedComponents: Partial<Record<string, Component>> = {}

/**
 * Register (or override) a field type. This is the application's extension
 * seam: field components with app-specific dependencies (writer, rich-text,
 * block-editor, ...) are registered at boot instead of living in the panel.
 *
 *   registerFieldType('writer', () => import('@/Components/Fields/WriterField.vue'))
 */
export function registerFieldType(type: string, loader: FieldLoader): void {
  fieldRegistry[type] = loader
  delete resolvedComponents[type]
}

/**
 * The types the package ships, as the manifest lists them. The PHP side's
 * `FieldComponents::BUILT_IN` is pinned to the same file, so a type added on
 * one side and not the other fails a test rather than a page.
 */
export function builtInFieldTypes(): string[] {
  return [...(builtInTypes as string[])]
}

/** Every type the registry can render right now: built-ins plus what the app registered. */
export function registeredFieldTypes(): string[] {
  return Object.keys(fieldRegistry)
}

export function hasFieldType(type: string): boolean {
  return type in fieldRegistry
}

/**
 * The types a set of field definitions needs that nothing has registered,
 * sub-fields included — a set's inputs and a repeater's rows render through
 * this registry too. Unique, in first-use order.
 */
export function missingFieldTypes(fields: ReadonlyArray<{ type?: string; fields?: unknown }>): string[] {
  const missing: string[] = []

  const walk = (list: ReadonlyArray<{ type?: string; fields?: unknown }>): void => {
    for (const field of list) {
      if (typeof field?.type === 'string' && !hasFieldType(field.type) && !missing.includes(field.type)) {
        missing.push(field.type)
      }
      if (Array.isArray(field?.fields)) {
        walk(field.fields as ReadonlyArray<{ type?: string; fields?: unknown }>)
      }
    }
  }

  walk(fields)

  return missing
}

/** What to tell whoever declared a type nothing renders: the fix, not just the fact. */
export function unknownFieldTypeMessage(types: string[]): string {
  const list = types.map((t) => `"${t}"`).join(', ')
  const recipe = types.map((t) => `    '${t}': () => import('./Fields/${pascal(t)}Field.vue'),`).join('\n')

  return `Unknown field type${types.length > 1 ? 's' : ''} ${list}. Register a component for it at boot:\n`
    + `  createPanel({\n    fields: {\n${recipe}\n    },\n  })\n`
    + `Registered types: ${registeredFieldTypes().join(', ')}.`
}

function pascal(type: string): string {
  return type.replace(/(^|[-_])(\w)/g, (_, __, c: string) => c.toUpperCase())
}

export async function resolveFieldComponent(type: string): Promise<Component> {
  if (!resolvedComponents[type]) {
    const loader = fieldRegistry[type]
    if (!loader) {
      throw new Error(unknownFieldTypeMessage([type]))
    }
    const mod = await loader()
    resolvedComponents[type] = (mod as { default: Component }).default ?? (mod as Component)
  }
  return resolvedComponents[type]!
}
