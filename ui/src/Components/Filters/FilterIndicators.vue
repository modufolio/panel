<template>
  <!--
    What is currently narrowing the list, and how to undo it.

    Filters live behind a popover, so without this the only sign that a result
    set is small is a count badge on a closed button — and "no results" reads
    as "no such records" rather than "you filtered them out three days ago".
  -->
  <div
    v-if="indicators.length > 0"
    class="ui-filter-indicators flex flex-wrap items-center gap-2 border-b border-line px-4 py-2.5"
    role="region"
    :aria-label="`${indicators.length} active ${indicators.length === 1 ? 'filter' : 'filters'}`"
  >
    <span class="text-xs font-medium text-ink-3">Filtered by</span>

    <span
      v-for="indicator in indicators"
      :key="indicator.key"
      class="inline-flex items-center gap-1 rounded-full bg-primary-surface py-1 pl-2.5 pr-1 text-xs font-medium text-primary-on-surface ring-1 ring-inset ring-primary/25"
    >
      <span class="text-primary">{{ indicator.label }}:</span>
      <span :title="indicator.value">{{ truncate(indicator.value) }}</span>
      <button
        type="button"
        class="ml-0.5 rounded-full p-0.5 text-primary transition-colors hover:bg-pressed hover:text-primary-on-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
        :aria-label="`Remove ${indicator.label} filter`"
        @click="$emit('remove', indicator.key)"
      >
        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </button>
    </span>

    <button
      v-if="indicators.length > 1"
      type="button"
      class="ml-1 text-xs font-medium text-ink-3 underline-offset-2 hover:text-ink hover:underline"
      @click="$emit('clear')"
    >
      Clear all
    </button>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'

export interface FilterIndicator {
  /** Filter key, handed back on remove. */
  key: string
  label: string
  /** The active value, already in words. */
  value: string
}

const props = defineProps({
  indicators: {
    type: Array as PropType<FilterIndicator[]>,
    default: () => [],
  },
  /** Longest value shown before it is cut; the full text stays in the title. */
  limit: {
    type: Number,
    default: 40,
  },
})

defineEmits<{
  remove: [key: string]
  clear: []
}>()

function truncate(value: string): string {
  return value.length > props.limit ? `${value.slice(0, props.limit - 1)}…` : value
}
</script>
