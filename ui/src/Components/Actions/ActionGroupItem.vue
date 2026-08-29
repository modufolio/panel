<template>
  <button
    type="button"
    role="menuitem"
    @click="handleClick"
    class="ui-action-group-item w-full flex items-center gap-3 px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100 transition-colors"
    :class="itemClasses"
  >
    <!-- Icon (string name or component) -->
    <template v-if="icon">
      <Icon v-if="typeof icon === 'string'" :name="icon" class="w-5 h-5 shrink-0" :class="iconColorClass" />
      <component v-else :is="icon" class="w-5 h-5 shrink-0" :class="iconColorClass" />
    </template>
    <!-- Slot icon (legacy / custom) -->
    <slot v-else-if="$slots.default" />

    <!-- Label -->
    <span class="flex-1">{{ label }}</span>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '../Core/Icon.vue'

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  icon: {
    type: [Object, String],
    default: null,
  },
  color: {
    type: String,
    default: 'gray',
    validator: (value: string) => ['primary', 'success', 'danger', 'warning', 'info', 'gray'].includes(value),
  },
})

const emit = defineEmits(['click'])

const itemClasses = computed(() => {
  const colorClasses: Record<string, string> = {
    danger: 'hover:bg-danger-50 hover:text-danger-700',
  }
  return colorClasses[props.color] || ''
})

const iconColorClass = computed(() => {
  const colorClasses: Record<string, string> = {
    primary: 'text-primary-600',
    success: 'text-success-600',
    danger: 'text-danger-600',
    warning: 'text-warning-600',
    info: 'text-info-600',
    gray: 'text-gray-600',
  }
  return colorClasses[props.color]
})

function handleClick() {
  emit('click')
}
</script>
