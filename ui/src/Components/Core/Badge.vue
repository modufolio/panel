<template>
  <span
    class="ui-badge inline-flex items-center justify-center font-medium tabular-nums rounded-full"
    :class="[colorClasses, sizeClasses]"
  >
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  label: {
    type: [String, Number],
    default: '',
  },
  color: {
    type: String,
    default: 'gray',
    validator: (v: string) => ['primary', 'success', 'danger', 'warning', 'info', 'gray'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v: string) => ['sm', 'md'].includes(v),
  },
})

const colorClasses = computed(() => {
  const map: Record<string, string> = {
    primary: 'bg-primary-100 text-primary-700',
    success: 'bg-success-100 text-success-700',
    danger: 'bg-danger-100 text-danger-700',
    warning: 'bg-warning-100 text-warning-700',
    info: 'bg-info-100 text-info-700',
    gray: 'bg-gray-100 text-gray-600',
  }
  return map[props.color]
})

const sizeClasses = computed(() => {
  const map: Record<string, string> = {
    sm: 'min-w-[1.125rem] h-[1.125rem] px-1 text-[10px]',
    md: 'min-w-[1.25rem] h-5 px-1.5 text-xs',
  }
  return map[props.size]
})
</script>
