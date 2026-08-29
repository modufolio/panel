<template>
  <button
    :type="type"
    :aria-label="computedAriaLabel"
    class="ui-action-btn inline-flex items-center justify-center gap-2 font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none"
    :class="buttonClasses"
    :data-variant="variant"
    :data-size="size"
    :disabled="disabled"
    @click="$emit('click', $event)"
  >
    <!-- Icon (leading) -->
    <template v-if="icon && iconPosition === 'before'">
      <Icon v-if="typeof icon === 'string'" :name="icon" class="shrink-0" :class="iconSizeClass" />
      <component v-else :is="icon" ref="leadingIconRef" class="shrink-0" :class="iconSizeClass" />
    </template>

    <!-- Slot for custom icon -->
    <slot name="icon-before" />

    <!-- Label -->
    <span v-if="label">{{ label }}</span>
    <slot v-else />

    <!-- Icon (trailing) -->
    <template v-if="icon && iconPosition === 'after'">
      <Icon v-if="typeof icon === 'string'" :name="icon" class="shrink-0" :class="iconSizeClass" />
      <component v-else :is="icon" ref="trailingIconRef" class="shrink-0" :class="iconSizeClass" />
    </template>

    <!-- Slot for custom icon -->
    <slot name="icon-after" />
  </button>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted, useSlots, type PropType } from 'vue'
import Icon from '../Core/Icon.vue'

const props = defineProps({
  label: {
    type: String,
    default: '',
  },
  ariaLabel: {
    type: String,
    default: '',
  },
  icon: {
    type: [Object, String, Function],
    default: null,
  },
  iconPosition: {
    type: String,
    default: 'before',
    validator: (value: unknown) => ['before', 'after'].includes(value as string),
  },
  color: {
    type: String,
    default: 'primary',
    validator: (value: unknown) => ['primary', 'success', 'danger', 'warning', 'info', 'gray'].includes(value as string),
  },
  variant: {
    type: String,
    default: 'filled',
    validator: (value: unknown) => ['filled', 'outlined', 'text'].includes(value as string),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value: unknown) => ['sm', 'md', 'lg'].includes(value as string),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String as PropType<'button' | 'submit' | 'reset'>,
    default: 'button',
    validator: (value: unknown) => ['button', 'submit', 'reset'].includes(value as string),
  },
})

defineEmits(['click'])

const slots = useSlots()

const leadingIconRef = ref<SVGElement | null>(null)
const trailingIconRef = ref<SVGElement | null>(null)

// Set aria-hidden programmatically because heroicons hardcode aria-hidden="true"
const applyAriaHidden = () => {
  const hasLabel = !!props.label || !!slots.default
  const ariaHiddenVal = String(hasLabel)
  ;[leadingIconRef, trailingIconRef].forEach(iconRef => {
    const el = iconRef.value as SVGElement | null
    if (el?.setAttribute) el.setAttribute('aria-hidden', ariaHiddenVal)
  })
}

onMounted(applyAriaHidden)
watch(() => props.label, applyAriaHidden)

// Computed aria-label: use explicit ariaLabel, fall back to label
const computedAriaLabel = computed(() => {
  if (props.ariaLabel) return props.ariaLabel
  if (props.label) return props.label
  return undefined
})

const buttonClasses = computed(() => {
  const baseClasses = 'rounded-lg'

  const sizeClasses: Record<string, string> = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-3.5 py-2 text-sm',
    lg: 'px-4 py-2.5 text-base',
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
    baseClasses,
    sizeClasses[props.size],
    colorClasses[props.variant][props.color],
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
