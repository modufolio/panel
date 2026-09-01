<template>
  <!-- The label belongs beside the box, not above it, so this composes the
       field parts itself rather than using FieldPrimitive's stacked frame.
       The help and error keep the control's left offset, which is why they
       are placed here too. -->
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
        <FieldLabel :for="id" :required="required" spacing="none" class="cursor-pointer">
          {{ label }}
        </FieldLabel>
        <p v-if="description" :id="`${id}-description`" class="text-gray-500 mt-0.5">
          {{ description }}
        </p>
      </div>
    </div>

    <FieldDescription v-if="help" :id="`${id}-help`" class="mt-2 ml-9">{{ help }}</FieldDescription>
    <FieldMessage v-if="error" :id="`${id}-error`" class="mt-2 ml-9">{{ error }}</FieldMessage>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import FieldDescription from './FieldDescription.vue'
import FieldLabel from './FieldLabel.vue'
import FieldMessage from './FieldMessage.vue'
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
