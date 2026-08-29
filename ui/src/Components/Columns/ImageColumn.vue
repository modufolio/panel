<template>
  <div class="ui-image-column">
    <img
      v-if="src"
      :src="src"
      :alt="alt"
      class="ui-image-column-img"
      :class="imageClasses"
    />
    <div
      v-else
      class="ui-image-column-placeholder"
      :class="imageClasses"
    >
      <svg class="w-1/2 h-1/2 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
      </svg>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  src: {
    type: String,
    default: '',
  },
  alt: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'md',
    validator: (value: unknown) => ['sm', 'md', 'lg', 'xl'].includes(value as string),
  },
  rounded: {
    type: String,
    default: 'full',
    validator: (value: unknown) => ['none', 'sm', 'md', 'lg', 'full'].includes(value as string),
  },
})

const imageClasses = computed(() => {
  const sizeClasses: Record<string, string> = {
    sm: 'w-6 h-6',
    md: 'w-8 h-8',
    lg: 'w-10 h-10',
    xl: 'w-12 h-12',
  }

  const roundedClasses: Record<string, string> = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    full: 'rounded-full',
  }

  return [
    sizeClasses[props.size],
    roundedClasses[props.rounded],
    'object-cover',
  ]
})
</script>