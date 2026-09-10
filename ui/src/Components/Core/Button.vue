<template>
  <component
    :is="resolvedAs"
    v-bind="elementAttrs"
    class="ui-btn inline-flex items-center justify-center gap-2 font-medium transition-colors disabled:opacity-50 disabled:pointer-events-none"
    :class="buttonClasses"
    :data-variant="variant"
    :data-color="resolvedColor"
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
import { isSemanticColorInput, semanticColor } from '../../Utils/colors'

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
    validator: isSemanticColorInput,
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

/**
 * Colour and variant are `data-` attributes rather than class strings: the
 * skin is eighteen combinations of six roles and three variants, and
 * `styles/components.css` expresses it as tokens the attributes reassign. What
 * is left here is geometry, which no theme touches.
 */
const resolvedColor = computed(() => semanticColor(props.color))

const buttonClasses = computed(() => {
  const sizeClasses: Record<string, string> = {
    sm: 'px-2.5 py-1.5 text-xs rounded-md',
    md: 'px-3.5 py-2 text-sm rounded-lg',
    lg: 'px-4 py-2.5 text-base rounded-lg',
  }

  return [
    sizeClasses[props.size],
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
