<template>
  <component
    :is="resolvedAs"
    v-bind="elementAttrs"
    class="ui-btn inline-flex items-center justify-center gap-2 font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none"
    :class="buttonClasses"
    @click="!loading && $emit('click', $event)"
  >
    <!-- Loading spinner -->
    <svg
      v-if="loading"
      class="animate-spin shrink-0"
      :class="iconSizeClass"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
    </svg>

    <!-- Leading icon -->
    <component
      v-else-if="icon && iconPosition === 'before'"
      :is="icon"
      class="shrink-0"
      :class="iconSizeClass"
      aria-hidden="true"
    />

    <!-- Label / default slot -->
    <span v-if="label">{{ label }}</span>
    <slot v-else />

    <!-- Trailing icon -->
    <component
      v-if="!loading && icon && iconPosition === 'after'"
      :is="icon"
      class="shrink-0"
      :class="iconSizeClass"
      aria-hidden="true"
    />
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  label: {
    type: String,
    default: '',
  },
  as: {
    type: String,
    default: 'button',
    validator: (v: string) => ['button', 'a', 'link'].includes(v),
  },
  href: {
    type: String,
    default: null,
  },
  type: {
    type: String,
    default: 'button',
    validator: (v: string) => ['button', 'submit', 'reset'].includes(v),
  },
  icon: {
    type: [Object, Function],
    default: null,
  },
  iconPosition: {
    type: String,
    default: 'before',
    validator: (v: string) => ['before', 'after'].includes(v),
  },
  color: {
    type: String,
    default: 'primary',
    validator: (v: string) => ['primary', 'success', 'danger', 'warning', 'info', 'gray'].includes(v),
  },
  variant: {
    type: String,
    default: 'filled',
    validator: (v: string) => ['filled', 'outlined', 'text'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v: string) => ['sm', 'md', 'lg'].includes(v),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['click'])

const resolvedAs = computed(() => {
  if (props.as === 'link') return Link
  if (props.as === 'a') return 'a'
  return 'button'
})

const elementAttrs = computed(() => {
  if (props.as === 'button') {
    return { type: props.type, disabled: props.disabled || props.loading }
  }
  return { href: props.href }
})

const buttonClasses = computed(() => {
  const sizeClasses: Record<string, string> = {
    sm: 'px-2.5 py-1.5 text-xs rounded-md',
    md: 'px-3.5 py-2 text-sm rounded-lg',
    lg: 'px-4 py-2.5 text-base rounded-lg',
  }

  const colorClasses: Record<string, Record<string, string>> = {
    filled: {
      primary: 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-600',
      success: 'bg-success-600 text-white hover:bg-success-700 focus:ring-success-600',
      danger: 'bg-danger-600 text-white hover:bg-danger-700 focus:ring-danger-600',
      warning: 'bg-warning-600 text-white hover:bg-warning-700 focus:ring-warning-600',
      info: 'bg-info-600 text-white hover:bg-info-700 focus:ring-info-600',
      gray: 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-600',
    },
    outlined: {
      primary: 'border border-primary-600 text-primary-600 hover:bg-primary-50 focus:ring-primary-600',
      success: 'border border-success-600 text-success-600 hover:bg-success-50 focus:ring-success-600',
      danger: 'border border-danger-600 text-danger-600 hover:bg-danger-50 focus:ring-danger-600',
      warning: 'border border-warning-600 text-warning-600 hover:bg-warning-50 focus:ring-warning-600',
      info: 'border border-info-600 text-info-600 hover:bg-info-50 focus:ring-info-600',
      gray: 'border border-gray-300 text-gray-700 hover:bg-gray-50 focus:ring-gray-600',
    },
    text: {
      primary: 'text-primary-600 hover:bg-primary-50 focus:ring-primary-600',
      success: 'text-success-600 hover:bg-success-50 focus:ring-success-600',
      danger: 'text-danger-600 hover:bg-danger-50 focus:ring-danger-600',
      warning: 'text-warning-600 hover:bg-warning-50 focus:ring-warning-600',
      info: 'text-info-600 hover:bg-info-50 focus:ring-info-600',
      gray: 'text-gray-600 hover:bg-gray-50 focus:ring-gray-600',
    },
  }

  return [
    sizeClasses[props.size],
    colorClasses[props.variant][props.color],
    (props.disabled || props.loading) ? 'opacity-50 pointer-events-none' : '',
  ]
})

const iconSizeClass = computed(() => {
  const sizes: Record<string, string> = {
    sm: 'w-3.5 h-3.5',
    md: 'w-4 h-4',
    lg: 'w-5 h-5',
  }
  return sizes[props.size]
})
</script>
