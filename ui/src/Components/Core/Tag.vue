<template>
  <span
    class="ui-tag inline-flex items-center gap-1 font-medium rounded-md"
    :class="sizeClasses"
    :data-color="resolvedColor"
  >
    <!-- Status dot -->
    <span
      v-if="dot"
      class="ui-tag-dot rounded-full shrink-0"
      :class="dotSizeClass"
      aria-hidden="true"
    />

    <!-- Leading icon -->
    <component
      v-else-if="icon"
      :is="icon"
      class="shrink-0"
      :class="iconSizeClass"
      aria-hidden="true"
    />

    <slot>{{ label }}</slot>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { isSemanticColorInput, semanticColor } from '../../Utils/colors'

const props = defineProps({
  label: {
    type: String,
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
    validator: (v: string) => ['sm', 'md', 'lg'].includes(v),
  },
  dot: {
    type: Boolean,
    default: false,
  },
  icon: {
    type: [Object, Function],
    default: null,
  },
})

/** Tint, foreground and dot all hang off `data-color` in styles/components.css.
 *  The `purple` entry the map used to carry was the only hue in it; hues are
 *  translated by semanticColor, so it needed no case of its own. */
const resolvedColor = computed(() => semanticColor(props.color))

const sizeClasses = computed(() => {
  const map: Record<string, string> = {
    sm: 'px-1.5 py-0.5 text-xs',
    md: 'px-2 py-0.5 text-xs',
    lg: 'px-2.5 py-1 text-sm',
  }
  return map[props.size]
})

const dotSizeClass = computed(() => {
  const map: Record<string, string> = {
    sm: 'w-1.5 h-1.5',
    md: 'w-1.5 h-1.5',
    lg: 'w-2 h-2',
  }
  return map[props.size]
})

const iconSizeClass = computed(() => {
  const map: Record<string, string> = {
    sm: 'w-3 h-3',
    md: 'w-3.5 h-3.5',
    lg: 'w-4 h-4',
  }
  return map[props.size]
})
</script>
