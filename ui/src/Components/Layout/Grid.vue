<template>
  <div :class="gridClass">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  /**
   * Number of columns on mobile (default)
   */
  cols: {
    type: [Number, String],
    default: 1,
  },
  /**
   * Number of columns on small screens (sm)
   */
  sm: {
    type: [Number, String],
    default: null,
  },
  /**
   * Number of columns on medium screens (md)
   */
  md: {
    type: [Number, String],
    default: null,
  },
  /**
   * Number of columns on large screens (lg)
   */
  lg: {
    type: [Number, String],
    default: null,
  },
  /**
   * Number of columns on extra large screens (xl)
   */
  xl: {
    type: [Number, String],
    default: null,
  },
  /**
   * Gap between grid items
   * @values none, xs, sm, md, lg, xl
   */
  gap: {
    type: String,
    default: 'md',
    validator: (value: unknown) => ['none', 'xs', 'sm', 'md', 'lg', 'xl'].includes(value as string)
  },
})

const gridClass = computed(() => {
  const classes = ['grid']

  // Column configuration
  const colMap: Record<string | number, string> = {
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-4',
    5: 'grid-cols-5',
    6: 'grid-cols-6',
    7: 'grid-cols-7',
    8: 'grid-cols-8',
    9: 'grid-cols-9',
    10: 'grid-cols-10',
    11: 'grid-cols-11',
    12: 'grid-cols-12',
  }

  // Base columns
  if (colMap[props.cols]) {
    classes.push(colMap[props.cols])
  }

  // Responsive columns
  if (props.sm && colMap[props.sm]) {
    classes.push(`sm:${colMap[props.sm]}`)
  }
  if (props.md && colMap[props.md]) {
    classes.push(`md:${colMap[props.md]}`)
  }
  if (props.lg && colMap[props.lg]) {
    classes.push(`lg:${colMap[props.lg]}`)
  }
  if (props.xl && colMap[props.xl]) {
    classes.push(`xl:${colMap[props.xl]}`)
  }

  // Gap
  const gapMap: Record<string, string> = {
    'none': 'gap-0',
    'xs': 'gap-2',
    'sm': 'gap-4',
    'md': 'gap-6',
    'lg': 'gap-8',
    'xl': 'gap-10',
  }
  if (gapMap[props.gap]) {
    classes.push(gapMap[props.gap])
  }

  return classes.join(' ')
})
</script>

<style scoped>
/* Grid component - uses Tailwind grid utilities */
</style>
