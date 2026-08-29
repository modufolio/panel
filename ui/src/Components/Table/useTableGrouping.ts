import type { TableRecord } from './tableTypes'
import { getPath } from './tableSchema'

/**
 * Group headings for rows the server already ordered by the grouped field.
 * The client only draws the break where the value changes.
 */
export function useTableGrouping(groupBy: () => string, visibleRecords: () => TableRecord[]) {
  function groupValue(record: TableRecord): unknown {
    return groupBy() ? getPath(record, groupBy()) : null
  }

  /** True on the first row of each group, which is where the heading goes. */
  function isGroupStart(index: number): boolean {
    if (!groupBy()) return false
    if (index === 0) return true

    const rows = visibleRecords()

    return groupValue(rows[index - 1]) !== groupValue(rows[index])
  }

  function groupHeading(record: TableRecord): string {
    const value = groupValue(record)

    return value === null || value === undefined || value === '' ? '—' : String(value)
  }

  return { isGroupStart, groupHeading }
}
