import type { Component } from 'vue'

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

export async function resolveFieldComponent(type: string): Promise<Component> {
  if (!resolvedComponents[type]) {
    const loader = fieldRegistry[type]
    if (!loader) {
      throw new Error(
        `Unknown field type "${type}". Built-in types: ${Object.keys(fieldRegistry).join(', ')}. `
        + 'Custom types must be registered with registerFieldType() before use.',
      )
    }
    const mod = await loader()
    resolvedComponents[type] = (mod as { default: Component }).default ?? (mod as Component)
  }
  return resolvedComponents[type]!
}
