<template>
  <div class="ui-field-range" :class="widthClass">
    <div class="flex items-baseline justify-between gap-2 mb-1.5">
      <label
        v-if="label"
        :for="id"
        class="ui-field-label block text-sm font-medium text-gray-700"
        :class="{ 'after:content-[\'*\'] after:ml-0.5 after:text-danger-600': required }"
      >
        {{ label }}
      </label>

      <!-- Live readout. A range input gives no visible value of its own, and
           the thumb position alone does not tell you that 260 is 260. -->
      <span class="ui-field-range-value shrink-0 text-sm tabular-nums text-gray-500">
        {{ displayValue }}
      </span>
    </div>

    <input
      :id="id"
      type="range"
      :value="modelValue"
      :min="min"
      :max="max"
      :step="step"
      :disabled="disabled"
      :aria-describedby="ariaDescribedby"
      :aria-valuetext="suffix ? `${modelValue} ${suffix}` : undefined"
      class="ui-field-range-input w-full h-2 accent-primary-500 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
      @input="onInput"
    />

    <p v-if="help" :id="`${id}-help`" class="ui-field-help mt-1.5 text-sm text-gray-600">
      {{ help }}
    </p>

    <p v-if="error" :id="`${id}-error`" role="alert" class="ui-field-error mt-1.5 text-sm text-danger-600">
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useId } from '../../Primitives/useId'
import { useFieldWidth, fieldWidthProp } from './useFieldWidth'

const props = defineProps({
  ...fieldWidthProp,
  modelValue: { type: Number, default: 0 },
  id: { type: String, default: () => useId(undefined, 'field') },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  error: { type: String, default: '' },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 100 },
  step: { type: Number, default: 1 },
  /** Unit shown beside the value and announced to screen readers, e.g. 'px'. */
  suffix: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits<{ 'update:modelValue': [value: number] }>()

const widthClass = useFieldWidth(() => props.width)

// Built here rather than interpolated in the template: Vue collapses the
// whitespace between an expression and a following element, which would run
// the number and its unit together.
const displayValue = computed(() =>
  props.suffix ? `${props.modelValue} ${props.suffix}` : String(props.modelValue)
)

const ariaDescribedby = computed(() => {
  const ids = []
  if (props.help) ids.push(`${props.id}-help`)
  if (props.error) ids.push(`${props.id}-error`)
  return ids.length > 0 ? ids.join(' ') : undefined
})

// A range input's value is always a string; emit a number so consumers can
// bind it straight to a numeric model without .number on every call site.
function onInput(event: Event) {
  emit('update:modelValue', Number((event.target as HTMLInputElement).value))
}
</script>
