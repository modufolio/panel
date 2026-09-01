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

/**
 * One row of table data, as the server sends it. Cell values are `unknown`
 * and narrowed where they are read — the schema, not the row type, says what
 * each column holds.
 */
export type TableRecord = Record<string, unknown>

/** A row's primary key, as the server sends it. */
export type RecordId = string | number

/**
 * The row's `id`, for the calls that key a request on it (a delete, a bulk
 * post). Rows arrive untyped, so the shape is asserted here — once — rather
 * than at every call site.
 */
export function recordId(record: TableRecord): RecordId {
  return record.id as RecordId
}

/** Columns carry either `key` or `name`; every lookup goes through this. */
export function columnName(column: TableColumn): string | undefined {
  return column.key || column.name
}
