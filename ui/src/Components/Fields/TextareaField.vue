<template>
  <div class="ui-field-textarea" :class="widthClass">
    <label
      v-if="label"
      :for="id"
      class="ui-field-label block text-sm font-medium text-gray-700 mb-1.5"
      :class="{ 'after:content-[\'*\'] after:ml-0.5 after:text-danger-600': required }"
    >
      {{ label }}
    </label>

    <div class="ui-field-wrapper">
      <textarea
        :id="id"
        :value="modelValue"
        @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :rows="rows"
        :aria-describedby="ariaDescribedby"
        :aria-invalid="!!error"
        :aria-required="required"
        :class="textareaClasses"
        class="ui-input ui-field-textarea block w-full resize-y"
      />
    </div>

    <!-- Help Text -->
    <p v-if="help" :id="`${id}-help`" class="ui-field-help mt-1.5 text-sm text-gray-600">
      {{ help }}
    </p>

    <!-- Error Message -->
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
  modelValue: {
    type: [String, Number],
    default: '',
  },
  id: {
    type: String,
    default: () => useId(undefined, 'field'),
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  help: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  rows: {
    type: Number,
    default: 4,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:modelValue'])

const widthClass = useFieldWidth(() => props.width)

const ariaDescribedby = computed(() => {
  const ids = []
  if (props.help) ids.push(`${props.id}-help`)
  if (props.error) ids.push(`${props.id}-error`)
  return ids.length > 0 ? ids.join(' ') : undefined
})

const textareaClasses = computed(() => {
  const classes = ['px-3 py-2']

  if (props.error) {
    classes.push('border-danger-600 focus:border-danger-600 focus:ring-danger-600/20')
  }

  return classes
})
</script>
