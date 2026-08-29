<template>
  <div :class="containerClass">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  /**
   * Maximum width of the container
   * @values sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl, 6xl, 7xl, full, none
   */
  maxWidth: {
    type: String,
    default: '7xl',
    validator: (value: unknown) => [
      'sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', '7xl', 'full', 'none'
    ].includes(value as string)
  },
  /**
   * Horizontal padding
   * @values none, sm, md, lg
   */
  padding: {
    type: String,
    default: 'md',
    validator: (value: unknown) => ['none', 'sm', 'md', 'lg'].includes(value as string)
  },
  /**
   * Center the container
   */
  centered: {
    type: Boolean,
    default: true,
  },
})

const containerClass = computed(() => {
  const classes = []

  // Max width
  const maxWidthMap: Record<string, string> = {
    'sm': 'max-w-sm',
    'md': 'max-w-md',
    'lg': 'max-w-lg',
    'xl': 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
    '5xl': 'max-w-5xl',
    '6xl': 'max-w-6xl',
    '7xl': 'max-w-7xl',
    'full': 'max-w-full',
    'none': '',
  }
  if (maxWidthMap[props.maxWidth]) {
    classes.push(maxWidthMap[props.maxWidth])
  }

  // Padding
  const paddingMap: Record<string, string> = {
    'none': '',
    'sm': 'px-4',
    'md': 'px-4 sm:px-6 lg:px-8',
    'lg': 'px-6 sm:px-8 lg:px-12',
  }
  if (paddingMap[props.padding]) {
    classes.push(paddingMap[props.padding])
  }

  // Centering
  if (props.centered) {
    classes.push('mx-auto')
  }

  return classes.join(' ')
})
</script>

<style scoped>
/* Container component - no additional styles needed, uses Tailwind */
</style>
