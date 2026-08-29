<template>
  <span
    class="ui-badge inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-medium rounded-md"
    :class="badgeClasses"
  >
    <!-- Icon (optional) -->
    <component
      v-if="icon"
      :is="icon"
      class="w-3.5 h-3.5"
    />

    <!-- Label -->
    <span>{{ label }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    default: 'gray',
    validator: (value: unknown) => ['primary', 'success', 'danger', 'warning', 'info', 'gray'].includes(value as string),
  },
  icon: {
    type: [Object, String],
    default: null,
  },
})

const badgeClasses = computed(() => {
  const colorClasses: Record<string, string> = {
    primary: 'bg-primary-100 text-primary-700 ring-primary-600/20',
    success: 'bg-success-100 text-success-700 ring-success-600/20',
    danger: 'bg-danger-100 text-danger-700 ring-danger-600/20',
    warning: 'bg-warning-100 text-warning-700 ring-warning-600/20',
    info: 'bg-info-100 text-info-700 ring-info-600/20',
    gray: 'bg-gray-100 text-gray-700 ring-gray-600/20',
  }

  return [
    colorClasses[props.color],
    'ring-1 ring-inset',
  ]
})
</script>
