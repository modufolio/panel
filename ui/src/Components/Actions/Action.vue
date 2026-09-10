<template>
  <button
    :type="type"
    :aria-label="computedAriaLabel"
    class="ui-action-btn inline-flex items-center justify-center gap-2 font-medium transition-colors disabled:opacity-50 disabled:pointer-events-none"
    :class="buttonClasses"
    :data-variant="variant"
    :data-color="resolvedColor"
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
import { isSemanticColorInput, semanticColor } from '../../Utils/colors'

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
    validator: isSemanticColorInput,
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

/** Skin comes from `data-variant`/`data-color` via styles/components.css, which
 *  Core/Button.vue shares — see the note there. Only geometry is left here. */
const resolvedColor = computed(() => semanticColor(props.color))

const buttonClasses = computed(() => {
  const sizeClasses: Record<string, string> = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-3.5 py-2 text-sm',
    lg: 'px-4 py-2.5 text-base',
  }

  return ['rounded-lg', sizeClasses[props.size]]
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
