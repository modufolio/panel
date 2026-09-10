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
    primary: 'bg-primary-surface text-primary-on-surface ring-primary/20',
    success: 'bg-success-surface text-success-on-surface ring-success/20',
    danger: 'bg-danger-surface text-danger-on-surface ring-danger/20',
    warning: 'bg-warning-surface text-warning-on-surface ring-warning/20',
    info: 'bg-info-surface text-info-on-surface ring-info/20',
    gray: 'bg-gray-surface text-gray-on-surface ring-gray/20',
  }

  return [
    colorClasses[props.color],
    'ring-1 ring-inset',
  ]
})
</script>
