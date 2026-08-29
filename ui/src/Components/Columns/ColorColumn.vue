<template>
  <div class="ui-color-column flex items-center gap-2">
    <!-- Color Swatch -->
    <div
      class="ui-color-swatch"
      :class="swatchSizeClass"
      :style="{ backgroundColor: color }"
      :title="color"
    >
      <div class="ui-color-swatch-border"></div>
    </div>

    <!-- Color Label (optional) -->
    <span v-if="showLabel" class="ui-color-label text-sm text-gray-900">
      {{ label || color }}
    </span>

    <!-- Copy Button (optional) -->
    <button
      v-if="copyable"
      @click="copyToClipboard"
      type="button"
      class="ui-color-copy-btn text-gray-400 hover:text-gray-600 transition-colors"
      :title="`Copy ${color}`"
    >
      <svg
        v-if="!copied"
        class="w-5 h-5"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="2"
        stroke="currentColor"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
      </svg>
      <svg
        v-else
        class="w-5 h-5 text-success-600"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="2"
        stroke="currentColor"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps({
  /**
   * The color value (hex, rgb, rgba, hsl, etc.)
   */
  color: {
    type: String,
    required: true,
  },
  /**
   * Optional label to display next to the swatch
   */
  label: {
    type: String,
    default: null,
  },
  /**
   * Show the color value as a label
   */
  showLabel: {
    type: Boolean,
    default: true,
  },
  /**
   * Size of the color swatch
   * @values sm, md, lg
   */
  size: {
    type: String,
    default: 'md',
    validator: (value: unknown) => ['sm', 'md', 'lg'].includes(value as string)
  },
  /**
   * Enable copy to clipboard functionality
   */
  copyable: {
    type: Boolean,
    default: false,
  },
})

const copied = ref(false)

const swatchSizeClass = computed(() => {
  const sizeMap: Record<string, string> = {
    'sm': 'w-5 h-5',
    'md': 'w-6 h-6',
    'lg': 'w-8 h-8',
  }
  return sizeMap[props.size] || sizeMap.md
})

async function copyToClipboard() {
  try {
    await navigator.clipboard.writeText(props.color)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (err) {
    console.error('Failed to copy:', err)
  }
}
</script>