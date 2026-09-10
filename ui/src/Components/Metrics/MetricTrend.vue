<template>
  <div class="ui-metric-trend rounded-lg bg-surface p-5 shadow-sm ring-1 ring-hairline">
    <div class="flex items-center justify-between gap-2">
      <span class="truncate text-sm font-medium text-ink-2">{{ metric.label }}</span>
      <Icon v-if="metric.icon" :name="metric.icon" class="h-5 w-5 shrink-0 text-ink-3" />
    </div>

    <div class="mt-2 text-3xl font-semibold text-ink">{{ formatted }}</div>

    <!--
      Bars rather than a line: the series is counts per bucket, and a line
      between two counts implies values in between that were never measured.
      Plain divs rather than SVG — one element per bucket, and a title per bar
      so a reader can ask what a given day was without a tooltip library.
    -->
    <div
      class="mt-4 flex h-16 items-end gap-0.5"
      role="img"
      :aria-label="`${metric.label}: ${series.length} ${metric.bucket === 'month' ? 'months' : 'days'}`"
    >
      <div
        v-for="(point, index) in series"
        :key="point.label"
        class="flex-1 rounded-sm bg-primary-fill/80 transition-[height]"
        :class="heights[index] === 0 ? 'bg-surface-sunken' : ''"
        :style="{ height: `${Math.max(heights[index], 2)}%` }"
        :title="`${formatBucketLabel(point.label, metric.bucket)}: ${formatMetricValue(metric, point.value)}`"
      />
    </div>

    <div class="mt-2 flex justify-between text-xs text-ink-3">
      <span>{{ first }}</span>
      <span>{{ last }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import Icon from '../Core/Icon.vue'
import { barHeights, formatBucketLabel, formatMetricValue, type Metric } from './metrics'

const props = defineProps({
  metric: { type: Object as PropType<Metric>, required: true },
})

const series = computed(() => props.metric.series ?? [])
const heights = computed(() => barHeights(series.value))
const formatted = computed(() => formatMetricValue(props.metric, props.metric.value))

const first = computed(() =>
  series.value.length ? formatBucketLabel(series.value[0].label, props.metric.bucket) : '',
)
const last = computed(() =>
  series.value.length ? formatBucketLabel(series.value[series.value.length - 1].label, props.metric.bucket) : '',
)
</script>
