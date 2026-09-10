<template>
  <div class="ui-metric-partition rounded-lg bg-surface p-5 shadow-sm ring-1 ring-hairline">
    <div class="flex items-center justify-between gap-2">
      <span class="truncate text-sm font-medium text-ink-2">{{ metric.label }}</span>
      <Icon v-if="metric.icon" :name="metric.icon" class="h-5 w-5 shrink-0 text-ink-3" />
    </div>

    <p v-if="slices.length === 0" class="mt-3 text-sm text-ink-3">Nothing to break down yet.</p>

    <!--
      A bar per slice rather than a donut: the question a partition answers is
      "which is biggest, and by how much", and comparing bar lengths is easier
      than comparing angles. Sorted server-side, largest first.
    -->
    <ul v-else class="mt-3 space-y-2">
      <li v-for="(slice, index) in slices" :key="slice.label">
        <div class="flex items-baseline justify-between gap-2 text-sm">
          <span class="truncate text-label">{{ slice.label }}</span>
          <span class="shrink-0 tabular-nums text-ink-3">{{ formatMetricValue(metric, slice.value) }}</span>
        </div>
        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-sunken">
          <div
            class="h-full rounded-full"
            :class="colorClass(slice.color)"
            :style="{ width: `${Math.max(widths[index], 1)}%` }"
          />
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import Icon from '../Core/Icon.vue'
import { formatMetricValue, type Metric } from './metrics'

const props = defineProps({
  metric: { type: Object as PropType<Metric>, required: true },
})

const slices = computed(() => props.metric.slices ?? [])

/** Widths relative to the largest slice, so the biggest one always fills the row. */
const widths = computed(() => {
  const largest = Math.max(...slices.value.map((slice) => slice.value), 0)

  return slices.value.map((slice) => (largest <= 0 ? 0 : (slice.value / largest) * 100))
})

const colors: Record<string, string> = {
  primary: 'bg-primary-fill',
  success: 'bg-success-fill',
  danger: 'bg-danger-fill',
  warning: 'bg-warning-fill',
  info: 'bg-info-fill',
  gray: 'bg-gray-fill',
}

function colorClass(color?: string): string {
  return colors[color ?? ''] ?? colors.primary
}
</script>
