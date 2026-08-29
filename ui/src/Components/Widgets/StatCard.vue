<template>
  <div class="ui-stat-card bg-white rounded-lg shadow-sm ring-1 ring-gray-950/5 p-6">
    <!-- Label -->
    <div class="flex items-center justify-between">
      <div class="text-sm font-medium text-gray-600">
        {{ label }}
      </div>
      <div v-if="icon" class="text-gray-400">
        <Icon v-if="typeof icon === 'string'" :name="icon" class="w-5 h-5" />
        <component v-else :is="icon" class="w-5 h-5" />
      </div>
    </div>

    <!-- Value -->
    <div class="mt-2 flex items-baseline gap-2">
      <div class="text-3xl font-semibold text-gray-900">
        {{ formattedValue }}
      </div>

      <!-- Change Indicator -->
      <div v-if="change !== null" class="flex items-center gap-1 text-sm">
        <span :class="changeColorClass">
          <svg
            v-if="change > 0"
            class="w-4 h-4"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="2"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" />
          </svg>
          <svg
            v-else-if="change < 0"
            class="w-4 h-4"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="2"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" />
          </svg>
        </span>
        <span :class="changeColorClass" class="font-medium">
          {{ Math.abs(change) }}%
        </span>
      </div>
    </div>

    <!-- Description -->
    <div v-if="description" class="mt-2 text-sm text-gray-600">
      {{ description }}
    </div>

    <!-- Extra Content -->
    <div v-if="$slots.default" class="mt-4 pt-4 border-t border-gray-100">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '../Core/Icon.vue'

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String],
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
  change: {
    type: Number,
    default: null,
  },
  icon: {
    type: [Object, String],
    default: null,
  },
  format: {
    type: String,
    default: 'number',
    validator: (value: unknown) => ['number', 'currency', 'percent'].includes(value as string),
  },
  color: {
    type: String,
    default: 'primary',
    validator: (value: unknown) => ['primary', 'success', 'danger', 'warning', 'info', 'gray'].includes(value as string),
  },
})

const formattedValue = computed(() => {
  if (props.format === 'currency') {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(Number(props.value))
  } else if (props.format === 'percent') {
    return `${props.value}%`
  } else {
    return props.value.toLocaleString()
  }
})

const changeColorClass = computed(() => {
  if (props.change === null) return ''

  const isPositive = props.change > 0

  const colors: Record<string, string> = {
    primary: isPositive ? 'text-primary-600' : 'text-danger-600',
    success: 'text-success-600',
    danger: 'text-danger-600',
    warning: 'text-warning-600',
    info: 'text-info-600',
    gray: 'text-gray-600',
  }

  return colors[props.color]
})
</script>
