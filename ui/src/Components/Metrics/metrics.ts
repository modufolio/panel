/**
 * Client-side shape of a server-computed metric.
 *
 * Mirrors Metric\Metric's declaration plus whatever MetricCalculator added to
 * it. The numbers are already computed: nothing here re-derives a total, and a
 * card that wants a different period asks the server for it.
 */

export interface MetricSeriesPoint {
  /** `Y-m-d` for a daily bucket, `Y-m` for a monthly one. */
  label: string
  value: number
}

export interface MetricSlice {
  label: string
  value: number
  /** A panel colour token, from the metric's declared map. */
  color?: string
}

export interface Metric {
  key: string
  type: 'value' | 'trend' | 'partition' | (string & {})
  label: string
  icon?: string
  /** ISO currency code: render the number as money. */
  currency?: string
  decimals?: number
  /** 'day' | 'month' — how a trend's buckets are cut. */
  bucket?: string
  /** How many buckets the window holds. */
  window?: number
  /** value and trend: the headline number. */
  value?: number | null
  /** value with `compare()`: the same number for the preceding window. */
  previous?: number | null
  /** Percentage change against `previous`; null when there is nothing to compare to. */
  change?: number | null
  series?: MetricSeriesPoint[]
  slices?: MetricSlice[]
}

/** The metric's number, written the way the metric asked to be written. */
export function formatMetricValue(metric: Metric, value: number | null | undefined): string {
  if (value === null || value === undefined) return '—'

  if (metric.currency) {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: metric.currency,
      minimumFractionDigits: metric.decimals ?? 2,
      maximumFractionDigits: metric.decimals ?? 2,
    }).format(value)
  }

  return new Intl.NumberFormat(undefined, {
    minimumFractionDigits: metric.decimals ?? 0,
    maximumFractionDigits: metric.decimals ?? 0,
  }).format(value)
}

/**
 * A bucket label as a person reads it: `2026-09-08` → `8 Sep`, `2026-09` →
 * `Sep 2026`. Kept dumb on purpose — a chart axis is not the place to discover
 * that a date library is needed.
 */
export function formatBucketLabel(label: string, bucket?: string): string {
  const parts = label.split('-').map(Number)

  if (parts.some(Number.isNaN)) return label

  const [year, month, day] = parts
  const date = new Date(year, (month ?? 1) - 1, day ?? 1)

  if (Number.isNaN(date.getTime())) return label

  return bucket === 'month'
    ? date.toLocaleDateString(undefined, { month: 'short', year: 'numeric' })
    : date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}

/** Text colour for a change: up is good, down is not, flat is neither. */
export function changeClass(change: number | null | undefined): string {
  if (change === null || change === undefined || change === 0) return 'text-gray-500'

  return change > 0 ? 'text-success-700' : 'text-danger-700'
}

/** Bar heights as percentages of the largest value in the series. */
export function barHeights(series: MetricSeriesPoint[]): number[] {
  const largest = Math.max(...series.map((point) => point.value), 0)

  // Every bar the same height would be a lie about a flat series, and dividing
  // by zero would blank the chart: an all-zero window draws no bars at all.
  return series.map((point) => (largest <= 0 ? 0 : (point.value / largest) * 100))
}
