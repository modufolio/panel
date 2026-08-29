import { resolveRecordUrl, isEmptyValue, getPath, type SchemaColumn } from './tableSchema'

/**
 * The cell's displayed value, honouring `valueKey` so a column can render a
 * nested field while staying keyed on its own name.
 *
 * Resolved here rather than using Table.vue's `value` slot prop, which always
 * reads the bare column key — that returns the relation object, not its name.
 */
export function cellValue(column: SchemaColumn, record: Record<string, any>): unknown {
  return getPath(record, column.valueKey ?? column.key)
}

/** Where a cell links to, or null when it renders as plain content. */
export function cellLink(
  column: SchemaColumn,
  record: Record<string, any>,
  recordUrl: string | null | undefined,
): string | null {
  // An editable control handles its own clicks; wrapping it in a link would
  // navigate away the moment you tried to change the value.
  if (column.editable) return null

  // An empty cell renders its placeholder unlinked — a link to nowhere
  // labelled "—" is worse than plain text.
  if (isEmptyValue(cellValue(column, record))) return null

  if (column.urlTemplate) return resolveRecordUrl(column.urlTemplate, record)

  return column.linksToRecord ? resolveRecordUrl(recordUrl, record) : null
}

/**
 * A drill-down into a *related* record gets the accented style with an arrow;
 * a link to the row's own drawer stays muted and inline.
 */
export function cellLinkProps(
  column: SchemaColumn,
  record: Record<string, any>,
  recordUrl: string | null | undefined,
  queryParams: Record<string, any>,
): Record<string, any> {
  const href = cellLink(column, record, recordUrl)

  if (!href) return {}

  return column.showArrow
    ? { href, queryParams }
    : { href, color: 'gray', showArrow: false, queryParams }
}
