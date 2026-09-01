<template>
  <FieldPrimitive
    v-bind="{ width, label, help, error, required }"
    wrapper-class="ui-field-toggle-buttons border-0 p-0 m-0"
    as="fieldset"
  >
    <div class="ui-toggle-buttons inline-flex rounded-lg overflow-hidden border border-gray-300 shadow-sm">
      <button
        v-for="option in normalizedOptions"
        :key="String(option.value)"
        type="button"
        class="ui-toggle-button relative px-4 py-2 text-sm font-medium transition-all focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary-600"
        :class="buttonClasses(option)"
        :disabled="disabled || option.disabled"
        @click="selectOption(option.value)"
      >
        <!-- Icon -->
        <component
          v-if="option.icon"
          :is="option.icon"
          class="w-5 h-5"
          :class="{ 'mr-2': option.label }"
        />

        <!-- Label -->
        <span v-if="option.label">{{ option.label }}</span>
      </button>
    </div>
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { computed, type Component, type PropType } from 'vue'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'
import type { OptionItem } from './useBlueprint'

/** A blueprint option that may also carry an icon to render before its label. */
interface ToggleButtonOption extends OptionItem {
  icon?: Component | string | null
}

/** An option after bare strings and numbers have been given the object shape. */
interface NormalizedToggleOption {
  label: string | number
  value: OptionItem['value']
  disabled: boolean
  icon: Component | string | null
}

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: [String, Number, Boolean],
    default: null,
  },
  label: {
    type: String,
    default: '',
  },
  options: {
    type: Array as PropType<Array<ToggleButtonOption | string | number>>,
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
  // Color scheme for each option
  colors: {
    type: Object as PropType<Record<string, string>>,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:modelValue'])


const normalizedOptions = computed<NormalizedToggleOption[]>(() => {
  return props.options.map(option => {
    if (typeof option === 'string' || typeof option === 'number') {
      return {
        label: option,
        value: option,
        disabled: false,
        icon: null,
      }
    }
    return {
      label: option.label,
      value: option.value,
      disabled: option.disabled || false,
      icon: option.icon || null,
    }
  })
})

function selectOption(value: OptionItem['value']) {
  if (!props.disabled) {
    emit('update:modelValue', value)
  }
}

function buttonClasses(option: NormalizedToggleOption) {
  const isSelected = props.modelValue === option.value
  const color = props.colors[String(option.value)]

  const classes = []

  // Border classes
  classes.push('border-r', 'last:border-r-0')

  if (props.disabled || option.disabled) {
    classes.push('cursor-not-allowed', 'opacity-50')
  } else {
    classes.push('cursor-pointer')
  }

  if (isSelected) {
    // Selected state with colors
    if (color) {
      const colorClasses: Record<string, string> = {
        info: 'bg-info-600 dark:bg-info-600 text-white hover:bg-info-500 dark:hover:bg-info-500',
        warning: 'bg-warning-400 dark:bg-warning-600 text-warning-900 dark:text-warning-950 hover:bg-warning-300 dark:hover:bg-warning-500',
        success: 'bg-success-400 dark:bg-success-600 text-success-900 dark:text-success-950 hover:bg-success-300 dark:hover:bg-success-500',
        danger: 'bg-danger-600 dark:bg-danger-600 text-white hover:bg-danger-500 dark:hover:bg-danger-500',
        primary: 'bg-primary-600 dark:bg-primary-600 text-white hover:bg-primary-500 dark:hover:bg-primary-500',
        gray: 'bg-gray-600 dark:bg-gray-600 text-white hover:bg-gray-500 dark:hover:bg-gray-500',
      }
      classes.push(colorClasses[color] || colorClasses.primary)
    } else {
      classes.push('bg-primary-600', 'text-white', 'hover:bg-primary-500')
    }
  } else {
    // Unselected state
    classes.push('bg-white', 'text-gray-700', 'hover:bg-gray-50')
  }

  return classes
}
</script>

<style scoped>
.ui-toggle-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>
