<template>
  <div class="ui-empty flex flex-col items-center justify-center py-12 px-6 text-center">
    <!-- Icon -->
    <div class="mb-4 rounded-full bg-gray-100 p-4" :class="iconWrapperClass">
      <component
        v-if="icon"
        :is="icon"
        class="text-gray-400"
        :class="iconSizeClass"
        aria-hidden="true"
      />
      <svg
        v-else
        class="text-gray-400"
        :class="iconSizeClass"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
      </svg>
    </div>

    <!-- Heading -->
    <h3 v-if="heading" class="text-sm font-semibold text-gray-900 mb-1">
      {{ heading }}
    </h3>

    <!-- Text -->
    <p v-if="text" class="text-sm text-gray-500 max-w-sm">
      {{ text }}
    </p>

    <!-- Slot for custom content or action -->
    <div v-if="$slots.default" class="mt-4">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  icon: {
    type: [Object, Function],
    default: null,
  },
  heading: {
    type: String,
    default: '',
  },
  text: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'md',
    validator: (v: string) => ['sm', 'md', 'lg'].includes(v),
  },
})

const iconWrapperClass = computed(() => {
  const map: Record<string, string> = {
    sm: 'p-3',
    md: 'p-4',
    lg: 'p-5',
  }
  return map[props.size]
})

const iconSizeClass = computed(() => {
  const map: Record<string, string> = {
    sm: 'w-6 h-6',
    md: 'w-8 h-8',
    lg: 'w-10 h-10',
  }
  return map[props.size]
})
</script>
