import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MetricRow from '../src/Components/Metrics/MetricRow.vue'
import MetricCard from '../src/Components/Metrics/MetricCard.vue'
import MetricTrend from '../src/Components/Metrics/MetricTrend.vue'
import MetricPartition from '../src/Components/Metrics/MetricPartition.vue'
import { barHeights, formatBucketLabel, formatMetricValue } from '../src/Components/Metrics/metrics'
import type { Metric } from '../src/Components/Metrics/metrics'

describe('metric formatting', () => {
  it('writes a number the way the metric asked', () => {
    expect(formatMetricValue({ key: 'a', type: 'value', label: 'A' }, 1204)).toBe('1,204')
    expect(formatMetricValue({ key: 'a', type: 'value', label: 'A', decimals: 1 }, 8.25)).toBe('8.3')
    expect(formatMetricValue({ key: 'a', type: 'value', label: 'A', currency: 'EUR' }, 12)).toContain('12.00')
  })

  it('has something to say when there is no number at all', () => {
    expect(formatMetricValue({ key: 'a', type: 'value', label: 'A' }, null)).toBe('—')
  })

  it('reads a bucket label as a date, and leaves an unparseable one alone', () => {
    expect(formatBucketLabel('2026-09-08')).toMatch(/Sep/)
    expect(formatBucketLabel('2026-09', 'month')).toMatch(/2026/)
    expect(formatBucketLabel('not-a-date')).toBe('not-a-date')
  })

  it('scales bars against the largest value, and draws none for an empty window', () => {
    expect(barHeights([{ label: 'a', value: 5 }, { label: 'b', value: 0 }, { label: 'c', value: 10 }]))
      .toEqual([50, 0, 100])

    // All zero: dividing by the largest would be dividing by nothing.
    expect(barHeights([{ label: 'a', value: 0 }, { label: 'b', value: 0 }])).toEqual([0, 0])
  })
})

describe('the value card', () => {
  const metric: Metric = { key: 'movies', type: 'value', label: 'Movies', value: 1204 }

  it('shows the label and the formatted number', () => {
    const text = mount(MetricCard, { props: { metric } }).text()

    expect(text).toContain('Movies')
    expect(text).toContain('1,204')
  })

  it('shows a change, and says what it is against', () => {
    const text = mount(MetricCard, {
      props: { metric: { ...metric, value: 30, previous: 60, change: -50, window: 30, bucket: 'day' } },
    }).text()

    expect(text).toContain('50%')
    expect(text).toContain('60 in the previous 30 days')
  })

  it('shows no change when there was nothing to compare against', () => {
    const wrapper = mount(MetricCard, { props: { metric: { ...metric, previous: 0, change: null } } })

    expect(wrapper.text()).not.toContain('%')
  })
})

describe('the trend card', () => {
  const metric: Metric = {
    key: 'added',
    type: 'trend',
    label: 'Added',
    bucket: 'day',
    window: 3,
    value: 3,
    series: [
      { label: '2026-09-06', value: 2 },
      { label: '2026-09-07', value: 0 },
      { label: '2026-09-08', value: 1 },
    ],
  }

  it('draws one bar per bucket, including the quiet ones', () => {
    const wrapper = mount(MetricTrend, { props: { metric } })

    expect(wrapper.findAll('[role="img"] > div')).toHaveLength(3)
    expect(wrapper.text()).toContain('Added')
    expect(wrapper.text()).toContain('3')
  })

  it('titles each bar with its bucket and value, so a day can be read off it', () => {
    const bars = mount(MetricTrend, { props: { metric } }).findAll('[role="img"] > div')

    expect(bars[0].attributes('title')).toContain('2')
    expect(bars[1].attributes('title')).toContain('0')
  })
})

describe('the partition card', () => {
  const metric: Metric = {
    key: 'genre',
    type: 'partition',
    label: 'Genre',
    slices: [
      { label: 'Drama', value: 12, color: 'success' },
      { label: 'Sci-Fi', value: 4 },
    ],
  }

  it('lists each slice with its value', () => {
    const text = mount(MetricPartition, { props: { metric } }).text()

    expect(text).toContain('Drama')
    expect(text).toContain('12')
    expect(text).toContain('Sci-Fi')
  })

  it('uses the declared colour, and the default where none was declared', () => {
    const bars = mount(MetricPartition, { props: { metric } }).findAll('li div div')

    expect(bars[0].classes()).toContain('bg-success-500')
    expect(bars[1].classes()).toContain('bg-primary-500')
  })

  it('says so when there is nothing to break down', () => {
    const wrapper = mount(MetricPartition, { props: { metric: { ...metric, slices: [] } } })

    expect(wrapper.text()).toContain('Nothing to break down yet')
  })
})

describe('the metric row', () => {
  it('renders each metric through the card its type names', () => {
    const wrapper = mount(MetricRow, {
      props: {
        metrics: [
          { key: 'a', type: 'value', label: 'A', value: 1 },
          { key: 'b', type: 'trend', label: 'B', value: 1, series: [{ label: '2026-09-08', value: 1 }] },
          { key: 'c', type: 'partition', label: 'C', slices: [{ label: 'One', value: 1 }] },
        ] as Metric[],
      },
    })

    expect(wrapper.findComponent(MetricCard).exists()).toBe(true)
    expect(wrapper.findComponent(MetricTrend).exists()).toBe(true)
    expect(wrapper.findComponent(MetricPartition).exists()).toBe(true)
  })

  /** A schema can outlive the client that renders it. */
  it('falls back to the value card for a type it does not know', () => {
    const wrapper = mount(MetricRow, {
      props: { metrics: [{ key: 'a', type: 'sparkline', label: 'A', value: 7 }] as Metric[] },
    })

    expect(wrapper.findComponent(MetricCard).exists()).toBe(true)
    expect(wrapper.text()).toContain('7')
  })

  it('renders nothing at all when a resource declares no metrics', () => {
    expect(mount(MetricRow, { props: { metrics: [] } }).find('.ui-metric-row').exists()).toBe(false)
  })
})
