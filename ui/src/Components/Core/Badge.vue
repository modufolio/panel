<template>
  <span
    class="ui-badge inline-flex items-center justify-center font-medium tabular-nums rounded-full"
    :class="sizeClasses"
    :data-color="resolvedColor"
  >
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { isSemanticColorInput, semanticColor } from '../../Utils/colors'

const props = defineProps({
  label: {
    type: [String, Number],
    default: '',
  },
  color: {
    type: String,
    default: 'gray',
    validator: isSemanticColorInput,
  },
  size: {
    type: String,
    default: 'md',
    validator: (v: string) => ['sm', 'md'].includes(v),
  },
})

/** The tint and its foreground are `data-color` in styles/components.css; going
 *  through semanticColor means an unrecognised value renders grey rather than
 *  unstyled, which the old map could not do. */
const resolvedColor = computed(() => semanticColor(props.color))

const sizeClasses = computed(() => {
  const map: Record<string, string> = {
    sm: 'min-w-[1.125rem] h-[1.125rem] px-1 text-[10px]',
    md: 'min-w-[1.25rem] h-5 px-1.5 text-xs',
  }
  return map[props.size]
})
</script>
