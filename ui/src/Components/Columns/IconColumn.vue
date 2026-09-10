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
      primary: 'bg-primary-surface text-primary-on-surface',
      success: 'bg-success-surface text-success-on-surface',
      danger: 'bg-danger-surface text-danger-on-surface',
      warning: 'bg-warning-surface text-warning-on-surface',
      info: 'bg-info-surface text-info-on-surface',
      gray: 'bg-gray-surface text-gray-on-surface',
    }
    classes.push(colors[props.color])
  } else {
    const colors: Record<string, string> = {
      primary: 'ring-1 ring-inset ring-primary/20 text-primary',
      success: 'ring-1 ring-inset ring-success/20 text-success',
      danger: 'ring-1 ring-inset ring-danger/20 text-danger',
      warning: 'ring-1 ring-inset ring-warning/20 text-warning',
      info: 'ring-1 ring-inset ring-info/20 text-info',
      gray: 'ring-1 ring-inset ring-gray/20 text-gray',
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
    primary: 'text-primary',
    success: 'text-success',
    danger: 'text-danger',
    warning: 'text-warning',
    info: 'text-info',
    gray: 'text-gray',
  }
  return ['text-sm font-medium', colors[props.color]]
})
</script>
