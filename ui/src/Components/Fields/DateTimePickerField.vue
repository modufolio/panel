<template>
  <!-- Two controls, so the frame is a fieldset and its caption a legend:
       `<label for>` may only point at one of them. -->
  <FieldPrimitive
    v-bind="{ width, id, label, help, error, required }"
    wrapper-class="ui-field-datetime border-0 p-0 m-0"
    as="fieldset"
  >
    <!--
      Two controls, one value: a date and a time are read, typed and corrected
      separately, and a single text box that demands both at once is the reason
      datetime fields get retyped from scratch.
    -->
    <div class="flex items-start gap-2">
      <div class="min-w-0 flex-1">
        <DatePickerField
          :id="`${id}-date`"
          :model-value="datePart"
          :min="minDate"
          :max="maxDate"
          :disabled="disabled"
          :required="required"
          :error="error !== '' ? ' ' : ''"
          :aria-label="`${label} date`"
          width="full"
          @update:model-value="onDateChange"
        />
      </div>

      <div class="w-32 shrink-0">
        <TimePickerField
          :id="`${id}-time`"
          :model-value="timePart"
          :step="step"
          :disabled="disabled"
          :error="error !== '' ? ' ' : ''"
          :aria-label="`${label} time`"
          width="full"
          @update:model-value="onTimeChange"
        />
      </div>
    </div>
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useId } from '../../Primitives/useId'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'
import DatePickerField from './DatePickerField.vue'
import TimePickerField from './TimePickerField.vue'

const props = defineProps({
  ...fieldWidthProp,
  /**
   * `YYYY-MM-DDTHH:mm` — local wall-clock time, no zone. What a calendar entry
   * means by "14:00" is 14:00 where it happens; converting to UTC here would
   * make the stored value depend on the browser that typed it.
   */
  modelValue: { type: String, default: '' },
  id: { type: String, default: () => useId(undefined, 'field') },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  error: { type: String, default: '' },
  /** Bounds on the date half, as `YYYY-MM-DD`. */
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  step: { type: Number, default: 60 },
  /** Time used when a date is picked and no time has been given yet. */
  defaultTime: { type: String, default: '09:00' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])


/** Tolerates a stored `…:ss`, a space separator, and a trailing `Z`. */
const parts = computed(() => {
  const match = /^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}))?/.exec(props.modelValue.trim())
  return { date: match?.[1] ?? '', time: match?.[2] ?? '' }
})

const datePart = computed(() => parts.value.date)
const timePart = computed(() => parts.value.time)

const minDate = computed(() => props.min.slice(0, 10))
const maxDate = computed(() => props.max.slice(0, 10))

function commit(date: string, time: string): void {
  // No date means no moment, whatever the time says — emitting a bare time
  // would produce a value nothing can parse.
  emit('update:modelValue', date === '' ? '' : `${date}T${time || props.defaultTime}`)
}

function onDateChange(value: string): void {
  commit(value, timePart.value)
}

function onTimeChange(value: string): void {
  commit(datePart.value, value)
}
</script>
