<template>
  <div class="ui-icon-column inline-flex items-center gap-2">
    <span :class="iconWrapperClasses" class="inline-flex items-center justify-center rounded-full">
      <component :is="icon" :class="iconClasses" />
    </span>
    <span v-if="label" :class="labelClasses">{{ label }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  icon: {
    type: [Object, String],
    required: true,
  },
  label: {
    type: String,
    default: '',
  },
  color: {
    type: String,
    default: 'gray',
    validator: (value: unknown) => ['primary', 'success', 'danger', 'warning', 'info', 'gray'].includes(value as string),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value: unknown) => ['sm', 'md', 'lg', 'xl'].includes(value as string),
  },
  variant: {
    type: String,
    default: 'solid',
    validator: (value: unknown) => ['solid', 'outline'].includes(value as string),
  },
})

const iconWrapperClasses = computed(() => {
  const classes = []

  // Size
  const sizes: Record<string, string> = {
    sm: 'w-6 h-6',
    md: 'w-8 h-8',
    lg: 'w-10 h-10',
    xl: 'w-12 h-12',
  }
  classes.push(sizes[props.size])

  // Color & Variant
  if (props.variant === 'solid') {
    const colors: Record<string, string> = {
      primary: 'bg-primary-100 text-primary-600',
      success: 'bg-success-100 text-success-600',
      danger: 'bg-danger-100 text-danger-600',
      warning: 'bg-warning-100 text-warning-600',
      info: 'bg-info-100 text-info-600',
      gray: 'bg-gray-100 text-gray-600',
    }
    classes.push(colors[props.color])
  } else {
    const colors: Record<string, string> = {
      primary: 'ring-1 ring-inset ring-primary-600/20 text-primary-600',
      success: 'ring-1 ring-inset ring-success-600/20 text-success-600',
      danger: 'ring-1 ring-inset ring-danger-600/20 text-danger-600',
      warning: 'ring-1 ring-inset ring-warning-600/20 text-warning-600',
      info: 'ring-1 ring-inset ring-info-600/20 text-info-600',
      gray: 'ring-1 ring-inset ring-gray-600/20 text-gray-600',
    }
    classes.push(colors[props.color])
  }

  return classes
})

const iconClasses = computed(() => {
  const sizes: Record<string, string> = {
    sm: 'w-3 h-3',
    md: 'w-4 h-4',
    lg: 'w-5 h-5',
    xl: 'w-6 h-6',
  }
  return sizes[props.size]
})

const labelClasses = computed(() => {
  const colors: Record<string, string> = {
    primary: 'text-primary-700',
    success: 'text-success-700',
    danger: 'text-danger-700',
    warning: 'text-warning-700',
    info: 'text-info-700',
    gray: 'text-gray-700',
  }
  return ['text-sm font-medium', colors[props.color]]
})
</script>
