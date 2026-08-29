<template>
  <div class="ui-field-text" :class="widthClass">
    <label
      v-if="label"
      :for="id"
      class="ui-field-label block text-sm font-medium text-gray-700 mb-1.5"
      :class="{ 'after:content-[\'*\'] after:ml-0.5 after:text-danger-600': required }"
    >
      {{ label }}
    </label>

    <div class="ui-field-wrapper relative">
      <!-- Prefix Icon/Text -->
      <div v-if="prefix" class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
        <component v-if="typeof prefix !== 'string'" :is="prefix" class="w-5 h-5 text-gray-400" />
        <span v-else class="text-gray-500 text-sm">{{ prefix }}</span>
      </div>

      <!-- Input -->
      <input
        :id="id"
        :type="type"
        :value="modelValue"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :autocomplete="autocomplete"
        :aria-describedby="ariaDescribedby"
        :aria-invalid="!!error"
        :aria-required="required"
        :class="inputClasses"
        class="ui-input ui-field-input block w-full"
      />

      <!-- Suffix Icon/Text -->
      <div v-if="suffix" class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
        <component v-if="typeof suffix !== 'string'" :is="suffix" class="w-5 h-5 text-gray-400" />
        <span v-else class="text-gray-500 text-sm">{{ suffix }}</span>
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
  type: {
    type: String,
    default: 'text',
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
  prefix: {
    type: [String, Object],
    default: null,
  },
  suffix: {
    type: [String, Object],
    default: null,
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
  autocomplete: {
    type: String,
    default: 'off',
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

const inputClasses = computed(() => {
  const classes = []

  if (props.prefix) {
    classes.push('pl-10')
  } else {
    classes.push('px-3')
  }

  if (props.suffix) {
    classes.push('pr-10')
  } else if (!props.prefix) {
    classes.push('pr-3')
  }

  classes.push('py-2')

  if (props.error) {
    classes.push('border-danger-600 focus:border-danger-600 focus:ring-danger-600/20')
  }

  return classes
})
</script>
