import { computed } from 'vue'
import type { FilterIndicator } from '../Filters/FilterIndicators.vue'
import {
  defaultFilterValue,
  isFilterDefault,
  type SchemaFilter,
  type QueryCondition,
  type TableSchema,
} from './tableSchema'

interface FilterOptions {
  schema: () => TableSchema
  /** Current filter form values, keyed by filter key. */
  values: () => Record<string, unknown>
  /** Reports a filter change; the page owns the state and the request. */
  onChange: (key: string, value: unknown) => void
  /** Fired once after every filter has been cleared. */
  onReset: () => void
}

/**
 * The state behind the schema's filter controls: what counts as active, what
 * the chips above the table say, and how a filter is cleared.
 *
 * Grouping and ad-hoc conditions are filter-shaped here too — they are set,
 * counted and cleared through the same plumbing as a declared filter.
 */
export function useSchemaFilters({ schema, values, onChange, onReset }: FilterOptions) {
  const schemaFilters = computed<SchemaFilter[]>(() => schema().filters ?? [])
  const groups = computed(() => schema().groups ?? [])
  const constraints = computed(() => schema().constraints ?? [])

  /** The conditions the query builder currently holds. */
  const conditions = computed<QueryCondition[]>(
    () => (values().constraints as QueryCondition[] | undefined) ?? [],
  )

  /**
   * Column key the active grouping reads. The server orders by the matching
   * entity field; the client only needs the row field to detect the break.
   */
  const activeGroupField = computed<string>(() => {
    const key = values().group

    return typeof key === 'string' && key !== '' ? key : ''
  })

  /** Mirrors useListFilters' notion of "empty" so the badge count agrees. */
  function isFilterActive(filter: SchemaFilter): boolean {
    const value = values()[filter.key]

    if (value === null || value === undefined || value === '') return false
    // The default is what the server applies unasked; showing it is not filtering.
    if (isFilterDefault(filter, value)) return false
    if (Array.isArray(value)) return value.length > 0
    if (typeof value === 'object') {
      return Object.values(value as object).some((entry) => entry !== null && entry !== '')
    }

    return true
  }

  const hasFilterControls = computed(
    () => schemaFilters.value.length > 0 || groups.value.length > 0 || constraints.value.length > 0,
  )

  const activeFilterCount = computed(
    () => schemaFilters.value.filter(isFilterActive).length + conditions.value.length,
  )

  /**
   * A filter's active value in words, for the chips above the table.
   *
   * Reads the same option lists the controls do, so a chip says "Active", not
   * the `active` that travelled in the query string.
   */
  function describeFilterValue(filter: SchemaFilter, value: unknown): string {
    const labelFor = (raw: unknown): string =>
      filter.options?.find((option) => String(option.value) === String(raw))?.label ?? String(raw)

    if (Array.isArray(value)) return value.map(labelFor).join(', ')

    if (filter.type === 'ternary' || filter.type === 'trashed') {
      return String(value) === String(filter.trueValue ?? '1')
        ? (filter.trueLabel ?? 'Yes')
        : (filter.falseLabel ?? 'No')
    }

    if (filter.type === 'dateRange' && value !== null && typeof value === 'object') {
      const { from, to } = value as { from?: string; to?: string }

      if (from && to) return `${from} → ${to}`
      if (from) return `from ${from}`
      if (to) return `until ${to}`

      return ''
    }

    if (value !== null && typeof value === 'object') {
      return Object.values(value as Record<string, unknown>)
        .filter((entry) => entry !== null && entry !== '')
        .map(String)
        .join(' – ')
    }

    return labelFor(value)
  }

  const filterIndicators = computed<FilterIndicator[]>(() => {
    const chips: FilterIndicator[] = schemaFilters.value.filter(isFilterActive).map((filter) => ({
      key: filter.key,
      label: filter.label,
      value: describeFilterValue(filter, values()[filter.key]),
    }))

    // Ad-hoc conditions are summarised as one chip: they are built and cleared
    // as a set in the query builder, and listing each would crowd out the
    // named filters beside them.
    if (conditions.value.length > 0) {
      chips.push({
        key: 'constraints',
        label: 'Conditions',
        value: `${conditions.value.length} applied`,
      })
    }

    const group = values().group
    if (group !== undefined && group !== null && group !== '') {
      const groupLabel =
        groups.value.find((entry) => String(entry.value) === String(group))?.label ?? String(group)

      chips.push({ key: 'group', label: 'Grouped by', value: groupLabel })
    }

    return chips
  })

  /** Reset one filter to its own empty value, whatever shape that is. */
  function clearFilter(key: string): void {
    if (key === 'constraints') return onChange('constraints', [])
    if (key === 'group') return onChange('group', '')

    const filter = schemaFilters.value.find((entry) => entry.key === key)
    if (filter) onChange(filter.key, defaultFilterValue(filter))
  }

  function resetFilters(): void {
    for (const filter of schemaFilters.value) {
      onChange(filter.key, defaultFilterValue(filter))
    }

    if (constraints.value.length > 0) onChange('constraints', [])
    if (groups.value.length > 0) onChange('group', '')

    onReset()
  }

  return {
    schemaFilters,
    activeGroupField,
    hasFilterControls,
    activeFilterCount,
    filterIndicators,
    clearFilter,
    resetFilters,
  }
}
