<template>
  <div v-if="metrics.length" class="ui-metric-row mb-6 grid gap-4" :class="gridClass">
    <component
      :is="componentFor(metric.type)"
      v-for="metric in metrics"
      :key="metric.key"
      :metric="metric"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * The row of metric cards above a listing.
 *
 * Which card a metric renders through is its declared type; an unknown type
 * falls back to the plain value card rather than rendering nothing, so a
 * schema that outlives its client still shows the number it computed.
 */
import { computed, type Component, type PropType } from 'vue'
import MetricCard from './MetricCard.vue'
import MetricTrend from './MetricTrend.vue'
import MetricPartition from './MetricPartition.vue'
import type { Metric } from './metrics'

const props = defineProps({
  metrics: { type: Array as PropType<Metric[]>, default: () => [] },
})

const componentForType: Record<string, Component> = {
  value: MetricCard,
  trend: MetricTrend,
  partition: MetricPartition,
}

function componentFor(type: string): Component {
  return componentForType[type] ?? MetricCard
}

/** Up to four across, and never more columns than there are cards. */
const gridClass = computed(() => {
  const columns = Math.min(props.metrics.length, 4)

  return {
    1: 'sm:grid-cols-1',
    2: 'sm:grid-cols-2',
    3: 'sm:grid-cols-2 lg:grid-cols-3',
    4: 'sm:grid-cols-2 lg:grid-cols-4',
  }[columns] ?? 'sm:grid-cols-2 lg:grid-cols-4'
})
</script>
