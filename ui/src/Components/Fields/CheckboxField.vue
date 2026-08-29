<template>
  <div class="ui-field-checkbox" :class="widthClass">
    <div class="relative flex items-start">
      <div class="flex items-center h-6">
        <input
          :id="id"
          type="checkbox"
          :checked="modelValue"
          @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
          :disabled="disabled"
          :required="required"
          :aria-describedby="ariaDescribedby"
          :aria-invalid="!!error"
          :aria-required="required"
          class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-2 focus:ring-primary-600/20 focus:border-primary-600 disabled:bg-gray-50 disabled:cursor-not-allowed transition-colors"
          :class="{ 'border-danger-600 focus:border-danger-600 focus:ring-danger-600/20': error }"
        />
      </div>
      <div class="ml-3 text-sm leading-6">
        <label
          :for="id"
          class="font-medium text-gray-700 cursor-pointer"
          :class="{ 'after:content-[\'*\'] after:ml-0.5 after:text-danger-600': required }"
        >
          {{ label }}
        </label>
        <p v-if="description" :id="`${id}-description`" class="text-gray-500 mt-0.5">
          {{ description }}
        </p>
      </div>
    </div>

    <!-- Help Text -->
    <p v-if="help" :id="`${id}-help`" class="ui-field-help mt-2 ml-9 text-sm text-gray-600">
      {{ help }}
    </p>

    <!-- Error Message -->
    <p v-if="error" :id="`${id}-error`" role="alert" class="ui-field-error mt-2 ml-9 text-sm text-danger-600">
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
    type: Boolean,
    default: false,
  },
  id: {
    type: String,
    default: () => useId(undefined, 'field'),
  },
  label: {
    type: String,
    required: true,
  },
  description: {
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
  disabled: {
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
  if (props.description) ids.push(`${props.id}-description`)
  if (props.help) ids.push(`${props.id}-help`)
  if (props.error) ids.push(`${props.id}-error`)
  return ids.length > 0 ? ids.join(' ') : undefined
})
</script>
