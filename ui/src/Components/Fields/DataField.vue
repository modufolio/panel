<script setup lang="ts">
import { computed } from 'vue'
import { useId } from '../../Primitives/useId'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'

/**
 * Read-only raw data — the importer's parking spot. Objects and arrays
 * render as pretty JSON; scalars as themselves. Never editable, never
 * submitted differently than received.
 */
const props = defineProps({
  ...fieldWidthProp,
  /** Ties the label and any help/error text to the control. */
  id: { type: String, default: () => useId(undefined, 'field') },
  modelValue: { type: null, default: null },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
})


const rendered = computed(() => {
  if (props.modelValue == null) return '—'
  if (typeof props.modelValue === 'object') return JSON.stringify(props.modelValue, null, 2)
  return String(props.modelValue)
})
</script>

<template>
  <FieldPrimitive v-bind="{ width, id, label, help }" wrapper-class="ui-field-data">
    <pre class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600 whitespace-pre-wrap break-all max-h-64 overflow-y-auto">{{ rendered }}</pre>
  </FieldPrimitive>
</template>
