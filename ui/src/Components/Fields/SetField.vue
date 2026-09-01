<script setup lang="ts">
import { defineAsyncComponent, type Component, type PropType } from 'vue'
import FieldLabel from './FieldLabel.vue'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'
import { resolveFieldComponent } from './fieldRegistry'
import type { FieldDef } from './useBlueprint'

/**
 * A named group of sub-fields editing one value object — the single-row
 * sibling of RepeaterField ("SEO" with title/description/image). Sub-field
 * declarations arrive from the server's SetType exactly as the repeater's
 * do, rendered through the same registry.
 */
const props = defineProps({
  ...fieldWidthProp,
  modelValue: { type: Object as PropType<Record<string, unknown>>, default: () => ({}) },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  fields: { type: Array as PropType<FieldDef[]>, default: () => [] },
  errors: { type: Object as PropType<Record<string, string>>, default: () => ({}) },
})

const emit = defineEmits<{ 'update:model-value': [value: Record<string, unknown>] }>()


const subComponents: Record<string, Component> = {}

function subComponent(type: string): Component {
  return (subComponents[type] ??= defineAsyncComponent(() => resolveFieldComponent(type)))
}

function update(key: string, value: unknown): void {
  emit('update:model-value', { ...props.modelValue, [key]: value })
}
</script>

<template>
  <FieldPrimitive
    v-bind="{ width, help }"
    wrapper-class="ui-field-set rounded-lg border border-gray-300 bg-white p-4"
    as="fieldset"
  >
    <!-- The legend sits on the border rather than above it, so it is placed
         here with its own spacing instead of through the frame's label. -->
    <FieldLabel v-if="label" as="legend" spacing="none" class="px-1">{{ label }}</FieldLabel>

    <div class="grid grid-cols-12 gap-4">
      <component
        :is="subComponent(subField.type)"
        v-for="subField in fields"
        :key="subField.key"
        :model-value="modelValue?.[subField.key]"
        :label="subField.label"
        :width="subField.width ?? 'full'"
        :required="subField.required ?? false"
        :disabled="disabled"
        :error="errors[subField.key] ?? ''"
        v-bind="{ ...(subField.options ? { options: subField.options } : {}), ...(subField.props ?? {}) }"
        @update:model-value="(val: unknown) => update(subField.key, val)"
      />
    </div>
  </FieldPrimitive>
</template>
