<template>
  <div class="ui-field-time" :class="widthClass">
    <label
      v-if="label"
      :for="id"
      class="ui-field-label block text-sm font-medium text-gray-700 mb-1.5"
      :class="{ 'after:content-[\'*\'] after:ml-0.5 after:text-danger-600': required }"
    >
      {{ label }}
    </label>

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
        :aria-invalid="error !== '' ? 'true' : undefined"
        :aria-describedby="describedBy"
        class="ui-input ui-field-input block w-full"
        :class="{ 'border-danger-600 focus:border-danger-600 focus:ring-danger-600/20': error !== '' }"
        @input="onInput"
      />
    </div>

    <p v-if="help" :id="`${id}-help`" class="ui-field-help mt-1.5 text-sm text-gray-600">
      {{ help }}
    </p>

    <p v-if="error" :id="`${id}-error`" class="ui-field-error mt-1.5 text-sm text-danger-600" role="alert">
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

const widthClass = useFieldWidth(() => props.width)

const describedBy = computed(() => {
  const ids: string[] = []
  if (props.help) ids.push(`${props.id}-help`)
  if (props.error) ids.push(`${props.id}-error`)
  return ids.length > 0 ? ids.join(' ') : undefined
})

function onInput(event: Event): void {
  // A time input reports '' while it is partially filled, which is the right
  // model value: half a time is not a time.
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}
</script>
