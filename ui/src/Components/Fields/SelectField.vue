<template>
  <FieldPrimitive
    v-bind="{ width, id, label, help, error, required }"
    wrapper-class="ui-field-select"
    v-slot="{ describedBy, invalid }"
  >
    <div class="ui-field-wrapper relative">
      <select
        :id="id"
        :value="modelValue"
        @change="handleChange"
        :disabled="disabled"
        :required="required"
        :aria-describedby="describedBy"
        :aria-invalid="invalid"
        :aria-required="required"
        :class="selectClasses"
        class="ui-input ui-field-select block w-full pr-10 appearance-none"
      >
        <option v-if="placeholder" value="">{{ placeholder }}</option>
        <option
          v-for="option in normalizedOptions"
          :key="String(option.value)"
          :value="option.value"
          :disabled="option.disabled"
        >
          {{ option.label }}
        </option>
      </select>

      <!-- Chevron Icon -->
      <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
        <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
      </div>
    </div>
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import { useId } from '../../Primitives/useId'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'
import type { OptionItem } from './useBlueprint'

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: [String, Number, Boolean],
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
  options: {
    type: Array as PropType<Array<OptionItem | string | number>>,
    required: true,
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

const emit = defineEmits(['update:modelValue'])



const normalizedOptions = computed(() => {
  return props.options.map(option => {
    if (typeof option === 'string' || typeof option === 'number') {
      return { label: option, value: option, disabled: false }
    }
    return {
      label: option.label,
      value: option.value,
      disabled: option.disabled || false,
    }
  })
})

const handleChange = (event: Event): void => {
  const target = event.target as HTMLSelectElement
  const raw = target.value

  if (raw === '') {
    emit('update:modelValue', null)
    return
  }

  const match = normalizedOptions.value.find(opt => String(opt.value) === raw)
  emit('update:modelValue', match ? match.value : raw)
}

const selectClasses = computed(() => {
  const classes = ['px-3 py-2']

  if (props.error) {
    classes.push('border-danger-600 focus:border-danger-600 focus:ring-danger-600/20')
  }

  return classes
})
</script>
