<template>
  <span
    class="ui-tag inline-flex items-center gap-1 font-medium rounded-md ring-1 ring-inset"
    :class="[colorClasses, sizeClasses]"
  >
    <!-- Status dot -->
    <span
      v-if="dot"
      class="rounded-full shrink-0"
      :class="[dotSizeClass, dotColorClass]"
      aria-hidden="true"
    />

    <!-- Leading icon -->
    <component
      v-else-if="icon"
      :is="icon"
      class="shrink-0"
      :class="iconSizeClass"
      aria-hidden="true"
    />

    <slot>{{ label }}</slot>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  label: {
    type: String,
    default: '',
  },
  color: {
    type: String,
    default: 'gray',
    validator: (v: string) => ['primary', 'success', 'danger', 'warning', 'info', 'gray', 'purple'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v: string) => ['sm', 'md', 'lg'].includes(v),
  },
  dot: {
    type: Boolean,
    default: false,
  },
  icon: {
    type: [Object, Function],
    default: null,
  },
})

const colorClasses = computed(() => {
  const map: Record<string, string> = {
    primary: 'bg-primary-50 text-primary-700 ring-primary-600/20',
    success: 'bg-success-50 text-success-700 ring-success-600/20',
    danger: 'bg-danger-50 text-danger-700 ring-danger-600/20',
    warning: 'bg-warning-50 text-warning-700 ring-warning-600/20',
    info: 'bg-info-50 text-info-700 ring-info-600/20',
    gray: 'bg-gray-100 text-gray-700 ring-gray-600/20',
    purple: 'bg-purple-50 text-purple-700 ring-purple-600/20',
  }
  return map[props.color]
})

const sizeClasses = computed(() => {
  const map: Record<string, string> = {
    sm: 'px-1.5 py-0.5 text-xs',
    md: 'px-2 py-0.5 text-xs',
    lg: 'px-2.5 py-1 text-sm',
  }
  return map[props.size]
})

const dotSizeClass = computed(() => {
  const map: Record<string, string> = {
    sm: 'w-1.5 h-1.5',
    md: 'w-1.5 h-1.5',
    lg: 'w-2 h-2',
  }
  return map[props.size]
})

const dotColorClass = computed(() => {
  const map: Record<string, string> = {
    primary: 'bg-primary-500',
    success: 'bg-success-500',
    danger: 'bg-danger-500',
    warning: 'bg-warning-500',
    info: 'bg-info-500',
    gray: 'bg-gray-400',
    purple: 'bg-purple-500',
  }
  return map[props.color]
})

const iconSizeClass = computed(() => {
  const map: Record<string, string> = {
    sm: 'w-3 h-3',
    md: 'w-3.5 h-3.5',
    lg: 'w-4 h-4',
  }
  return map[props.size]
})
</script>
