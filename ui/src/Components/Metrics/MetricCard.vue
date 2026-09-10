<template>
  <div class="ui-metric-card rounded-lg bg-surface p-5 shadow-sm ring-1 ring-hairline">
    <div class="flex items-center justify-between gap-2">
      <span class="truncate text-sm font-medium text-ink-2">{{ metric.label }}</span>
      <Icon v-if="metric.icon" :name="metric.icon" class="h-5 w-5 shrink-0 text-ink-3" />
    </div>

    <div class="mt-2 flex items-baseline gap-2">
      <span class="text-3xl font-semibold text-ink">{{ formatted }}</span>

      <!--
        Only when there is something to compare with. A metric that declared
        no window has no previous period, and one whose previous period was
        empty has no percentage — saying "+100%" for the first week of data is
        a number that has to be read around.
      -->
      <span v-if="metric.change !== null && metric.change !== undefined" class="text-sm font-medium" :class="changeClass(metric.change)">
        {{ metric.change > 0 ? '↑' : metric.change < 0 ? '↓' : '' }}{{ Math.abs(metric.change) }}%
      </span>
    </div>

    <p v-if="comparison" class="mt-1 text-xs text-ink-3">{{ comparison }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import Icon from '../Core/Icon.vue'
import { changeClass, formatMetricValue, type Metric } from './metrics'

const props = defineProps({
  metric: { type: Object as PropType<Metric>, required: true },
})

const formatted = computed(() => formatMetricValue(props.metric, props.metric.value))

/** What the change is against, spelled out — a percentage alone says nothing about the period. */
const comparison = computed(() => {
  if (props.metric.previous === null || props.metric.previous === undefined) return ''

  const window = props.metric.window ?? 0
  const bucket = props.metric.bucket === 'month' ? 'month' : 'day'
  const period = window === 1 ? `previous ${bucket}` : `previous ${window} ${bucket}s`

  return `${formatMetricValue(props.metric, props.metric.previous)} in the ${period}`
})
</script>
