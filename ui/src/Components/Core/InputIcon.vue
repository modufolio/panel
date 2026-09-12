<template>
  <div
    class="absolute inset-y-0 flex items-center"
    :class="[
      side === 'left' ? 'left-0 pl-3' : 'right-0 pr-3',
      interactive ? undefined : 'pointer-events-none',
    ]"
  >
    <slot />
  </div>
</template>

<script setup lang="ts">

withDefaults(defineProps<{
  side?: 'left' | 'right'
  /** The slot is itself clickable (a clear button, a calendar toggle) — keep pointer events on. @default false */
  interactive?: boolean
}>(), {
  side: 'right',
  interactive: false,
})
</script>
