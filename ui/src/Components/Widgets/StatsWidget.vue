<template>
  <div class="ui-stats-widget">
    <div class="ui-stats-grid grid gap-4 lg:gap-6" :class="gridClass">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  columns: {
    type: Number,
    default: 3,
    validator: (value: unknown) => [1, 2, 3, 4].includes(value as number),
  },
})

const gridClass = computed(() => {
  const cols: Record<number, string> = {
    1: 'md:grid-cols-1',
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-3',
    4: 'md:grid-cols-4',
  }
  return cols[props.columns]
})
</script>