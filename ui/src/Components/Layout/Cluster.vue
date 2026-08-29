<template>
  <div :class="clusterClass">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  /**
   * Spacing between clustered items
   * @values none, xs, sm, md, lg, xl
   */
  space: {
    type: String,
    default: 'md',
    validator: (value: unknown) => ['none', 'xs', 'sm', 'md', 'lg', 'xl'].includes(value as string)
  },
  /**
   * Horizontal alignment of items
   * @values start, center, end, between, around, evenly
   */
  justify: {
    type: String,
    default: 'start',
    validator: (value: unknown) => ['start', 'center', 'end', 'between', 'around', 'evenly'].includes(value as string)
  },
  /**
   * Vertical alignment of items
   * @values start, center, end, baseline, stretch
   */
  align: {
    type: String,
    default: 'center',
    validator: (value: unknown) => ['start', 'center', 'end', 'baseline', 'stretch'].includes(value as string)
  },
  /**
   * Allow items to wrap to next line
   */
  wrap: {
    type: Boolean,
    default: true,
  },
})

const clusterClass = computed(() => {
  const classes = ['flex']

  // Spacing
  const spaceMap: Record<string, string> = {
    'none': 'gap-0',
    'xs': 'gap-1',
    'sm': 'gap-2',
    'md': 'gap-3',
    'lg': 'gap-4',
    'xl': 'gap-6',
  }
  if (spaceMap[props.space]) {
    classes.push(spaceMap[props.space])
  }

  // Justify content
  const justifyMap: Record<string, string> = {
    'start': 'justify-start',
    'center': 'justify-center',
    'end': 'justify-end',
    'between': 'justify-between',
    'around': 'justify-around',
    'evenly': 'justify-evenly',
  }
  if (justifyMap[props.justify]) {
    classes.push(justifyMap[props.justify])
  }

  // Align items
  const alignMap: Record<string, string> = {
    'start': 'items-start',
    'center': 'items-center',
    'end': 'items-end',
    'baseline': 'items-baseline',
    'stretch': 'items-stretch',
  }
  if (alignMap[props.align]) {
    classes.push(alignMap[props.align])
  }

  // Wrap
  if (props.wrap) {
    classes.push('flex-wrap')
  } else {
    classes.push('flex-nowrap')
  }

  return classes.join(' ')
})
</script>

<style scoped>
/* Cluster component - uses Tailwind flexbox utilities */
</style>
