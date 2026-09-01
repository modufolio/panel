<script setup lang="ts">
import { computed } from 'vue'
import { useId } from '../../Primitives/useId'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'

/**
 * An external embed stored as its URL. Renders a URL input plus an
 * open-in-new-tab preview link; oEmbed metadata resolution is the server's
 * job (EmbedType's `props.endpoint`), so the panel's CSP never has to allow
 * third-party origins.
 */
const props = defineProps({
  ...fieldWidthProp,
  /** Ties the label and any help/error text to the control. */
  id: { type: String, default: () => useId(undefined, 'field') },
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  error: { type: String, default: '' },
  placeholder: { type: String, default: 'https://…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits<{ 'update:model-value': [value: string] }>()


const previewable = computed(() => /^https?:\/\//.test(props.modelValue ?? ''))
</script>

<template>
  <FieldPrimitive
    v-bind="{ width, id, label, help, error, required }"
    wrapper-class="ui-field-embed"
    v-slot="{ describedBy, invalid }"
  >
    <div class="flex items-center gap-2">
      <input
        type="url"
        class="ui-input w-full rounded-md border-gray-300 shadow-sm"
        :class="{ 'border-danger-500': error }"
        :id="id"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :aria-describedby="describedBy"
        :aria-invalid="invalid"
        @input="emit('update:model-value', ($event.target as HTMLInputElement).value)"
      >
      <a
        v-if="previewable"
        :href="modelValue"
        target="_blank"
        rel="noopener noreferrer"
        class="shrink-0 text-sm text-primary-600 hover:underline"
      >Preview</a>
    </div>
  </FieldPrimitive>
</template>
