<template>
  <div class="ui-field-select" :class="widthClass">
    <label
      v-if="label"
      :for="id"
      class="ui-field-label block text-sm font-medium text-gray-700 mb-1.5"
      :class="{ 'after:content-[\'*\'] after:ml-0.5 after:text-danger-600': required }"
    >
      {{ label }}
    </label>

    <div class="ui-field-wrapper relative">
      <select
        :id="id"
        :value="modelValue"
        @change="handleChange"
        :disabled="disabled"
        :required="required"
        :aria-describedby="ariaDescribedby"
        :aria-invalid="!!error"
        :aria-required="required"
        :class="selectClasses"
        class="ui-input ui-field-select block w-full pr-10 appearance-none"
      >
        <option v-if="placeholder" value="">{{ placeholder }}</option>
        <option
          v-for="option in normalizedOptions"
          :key="option.value"
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
import { computed, type PropType } from 'vue'
import { useId } from '../../Primitives/useId'
import { useFieldWidth, fieldWidthProp } from './useFieldWidth'

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
    type: Array as PropType<any[]>,
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

const widthClass = useFieldWidth(() => props.width)

const ariaDescribedby = computed(() => {
  const ids = []
  if (props.help) ids.push(`${props.id}-help`)
  if (props.error) ids.push(`${props.id}-error`)
  return ids.length > 0 ? ids.join(' ') : undefined
})

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
