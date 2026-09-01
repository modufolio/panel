<template>
  <FieldPrimitive
    v-bind="{ width, id, label, help, error, required }"
    wrapper-class="ui-field-time"
    v-slot="{ describedBy, invalid }"
  >
    <div class="ui-field-wrapper relative">
      <!--
        The native control, not a bespoke one: it already gives keyboard entry,
        the viewer's own 12/24-hour convention, and the platform time picker on
        a phone — none of which a custom listbox of quarter-hours reproduces,
        and all of which people expect from a time field.
      -->
      <input
        :id="id"
        type="time"
        :value="modelValue"
        :min="min || undefined"
        :max="max || undefined"
        :step="step"
        :disabled="disabled"
        :required="required"
        :aria-invalid="invalid"
        :aria-describedby="describedBy"
        class="ui-input ui-field-input block w-full"
        :class="{ 'border-danger-600 focus:border-danger-600 focus:ring-danger-600/20': error !== '' }"
        @input="onInput"
      />
    </div>
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { useId } from '../../Primitives/useId'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'

defineProps({
  ...fieldWidthProp,
  /** `HH:mm` in 24-hour form, whatever the viewer's locale displays. */
  modelValue: { type: String, default: '' },
  id: { type: String, default: () => useId(undefined, 'field') },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  error: { type: String, default: '' },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  /** Seconds between selectable values; 60 keeps the control at whole minutes. */
  step: { type: Number, default: 60 },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])


function onInput(event: Event): void {
  // A time input reports '' while it is partially filled, which is the right
  // model value: half a time is not a time.
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}
</script>
