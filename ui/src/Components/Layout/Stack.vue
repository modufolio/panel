<template>
  <div :class="stackClass">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  /**
   * Spacing between stacked items
   * @values none, xs, sm, md, lg, xl, 2xl, 3xl
   */
  space: {
    type: String,
    default: 'md',
    validator: (value: unknown) => ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl'].includes(value as string)
  },
  /**
   * Horizontal alignment of items
   * @values start, center, end, stretch
   */
  align: {
    type: String,
    default: 'stretch',
    validator: (value: unknown) => ['start', 'center', 'end', 'stretch'].includes(value as string)
  },
  /**
   * Add divider between items
   */
  divider: {
    type: Boolean,
    default: false,
  },
})

const stackClass = computed(() => {
  const classes = ['flex', 'flex-col']

  // Spacing
  const spaceMap: Record<string, string> = {
    'none': 'space-y-0',
    'xs': 'space-y-1',
    'sm': 'space-y-2',
    'md': 'space-y-4',
    'lg': 'space-y-6',
    'xl': 'space-y-8',
    '2xl': 'space-y-10',
    '3xl': 'space-y-12',
  }
  if (spaceMap[props.space]) {
    classes.push(spaceMap[props.space])
  }

  // Alignment
  const alignMap: Record<string, string> = {
    'start': 'items-start',
    'center': 'items-center',
    'end': 'items-end',
    'stretch': 'items-stretch',
  }
  if (alignMap[props.align]) {
    classes.push(alignMap[props.align])
  }

  // Divider
  if (props.divider) {
    classes.push('divide-y', 'divide-gray-200')
  }

  return classes.join(' ')
})
</script>

<style scoped>
/* Stack component - uses Tailwind flexbox utilities */
</style>
