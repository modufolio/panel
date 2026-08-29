/** One column of a plain (non-schema) table. */
export interface TableColumn {
  key?: string
  name?: string
  label?: string
  sortable?: boolean
  width?: string
  headerClass?: string
  cellClass?: string
}

export type TableRecord = Record<string, any>

/** Columns carry either `key` or `name`; every lookup goes through this. */
export function columnName(column: TableColumn): string | undefined {
  return column.key || column.name
}
