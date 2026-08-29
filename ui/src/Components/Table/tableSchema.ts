/**
 * Client-side shape of a server-authored table schema.
 *
 * Mirrors App\Table\TableSchema / App\Table\Column. Declared in a .ts file
 * rather than inside the SFC so pages can import the types (`<script setup>`
 * cannot export type declarations).
 */

export type SchemaColumnType =
  | 'text'
  | 'select'
  | 'money'
  | 'numeric'
  | 'badge'
  | 'date'
  | 'boolean'
  | 'image'
  | 'icon'
  | 'color'

/**
 * An action button rendered inside every cell of a column.
 *
 * Mirrors App\Table\ColumnAction. Either navigates (`urlTemplate`) or calls the
 * handler the page registered under `name` in `cellActionHandlers`.
 */
export interface SchemaColumnAction {
  /** Key into the page's `cellActionHandlers` map. */
  name: string
  /** Accessible name and tooltip. */
  label: string
  icon?: string
  color?: string
  /** Render as a link. Dot-path placeholders, like `Column.urlTemplate`. */
  urlTemplate?: string
  /** Render inert when this field on the row is truthy. */
  disabledWhen?: string
  /** Omit entirely when this field on the row is truthy. */
  hiddenWhen?: string
  confirm?: boolean
  confirmMessage?: string
}

export interface SchemaColumn {
  /** Field on the record, and the `#cell-{key}` slot name for overrides. */
  key: string
  /** Duplicate of `key`; Table.vue reads either. */
  name: string
  label: string
  type: SchemaColumnType | (string & {})
  /** Resolved server-side against the list query — never declared by hand. */
  sortable: boolean
  toggleable?: boolean
  hiddenByDefault?: boolean
  /** Link the cell to the record using the schema's `recordUrl`. */
  linksToRecord?: boolean
  /** Read the displayed value from another field; dot-paths supported. */
  valueKey?: string
  /** Per-column link target, overriding `recordUrl`. Dot-path placeholders. */
  urlTemplate?: string
  /** Accented drill-down style with a trailing arrow. */
  showArrow?: boolean
  /** Rendered when the row's value is null or empty. */
  placeholder?: string
  /** Field holding a secondary line of text, computed by the presenter. */
  descriptionKey?: string
  width?: string
  /** Date format, for `type: 'date'`. */
  format?: string
  relative?: boolean
  /** Value → colour map, for `type: 'badge'` and read-only selects. */
  colors?: Record<string, string>
  /** Choices for `type: 'select'`. */
  options?: Array<{ label: string; value: string; class?: string }>
  /** Editable in place — the page supplies the save handler via `cellHandlers`. */
  editable?: boolean
  /** Render the control inert when this field on the row is truthy. */
  disabledWhen?: string
  /** Drop the control for a static badge when this field on the row is truthy. */
  readOnlyWhen?: string
  /** Action buttons rendered inside every cell, after the value. */
  actions?: SchemaColumnAction[]
  /** Font emphasis: 'medium' | 'bold'. */
  weight?: string
  /** Cell alignment: 'left' | 'center' | 'right'. */
  align?: string
  /** Static text colour token. */
  color?: string
  /** Registered icon name rendered before the value. */
  icon?: string
  /** Truncate after N characters, keeping the full value in the title. */
  limit?: number
  /** Offer click-to-copy on the cell. */
  copyable?: boolean
  /** Thumbnail size, for `type: 'image'`: 'sm' | 'md' | 'lg' | 'xl'. */
  size?: string
  /** Corner rounding, for `type: 'image'`: 'none' | 'sm' | 'md' | 'lg' | 'full'. */
  rounded?: string
  /** ISO currency code, for `type: 'money'`. */
  currency?: string
  /** Decimal places, for `type: 'numeric'`. */
  decimals?: number
}

/** Emphasis / alignment / colour → utility classes for a text cell. */
export function cellClasses(column: SchemaColumn): string {
  const classes: string[] = []

  if (column.weight === 'bold') classes.push('font-bold')
  else if (column.weight === 'medium') classes.push('font-medium')

  if (column.align === 'right') classes.push('text-right')
  else if (column.align === 'center') classes.push('text-center')

  const colors: Record<string, string> = {
    primary: 'text-primary-600',
    success: 'text-success-700',
    danger: 'text-danger-700',
    warning: 'text-warning-700',
    info: 'text-info-700',
    gray: 'text-gray-500',
  }
  if (column.color && colors[column.color]) classes.push(colors[column.color])

  return classes.join(' ')
}

/** Truncate for display; the untruncated value is kept as a title attribute. */
export function truncate(value: string, limit?: number): string {
  if (!limit || value.length <= limit) return value

  return value.slice(0, limit).trimEnd() + '…'
}

/**
 * Format a cell value for display.
 *
 * Kept client-side so re-sorting or a partial reload doesn't need the server
 * to re-render text it already has.
 */
export function formatValue(column: SchemaColumn, value: unknown): string {
  if (column.type === 'money') {
    const amount = Number(value)
    if (Number.isNaN(amount)) return String(value)

    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: column.currency ?? 'EUR',
    }).format(amount)
  }

  if (column.type === 'numeric') {
    const amount = Number(value)
    if (Number.isNaN(amount)) return String(value)

    return new Intl.NumberFormat(undefined, {
      minimumFractionDigits: column.decimals ?? 0,
      maximumFractionDigits: column.decimals ?? 0,
    }).format(amount)
  }

  return String(value)
}

export type SchemaFilterType =
  | 'select'
  | 'multiSelect'
  | 'ternary'
  | 'trashed'
  | 'dateRange'

export interface SchemaFilter {
  /** Query-param name and the key its value lives under in the filter form. */
  key: string
  type: SchemaFilterType | (string & {})
  label: string
  placeholder?: string
  options?: Array<{ label: string; value: string }>
  /**
   * The server capped the option list — only sent when it did. The control
   * shows this rather than ending the list silently, so a value that exists
   * but is unreachable from the dropdown is never mistaken for absent.
   */
  optionsTruncated?: boolean
  trueLabel?: string
  falseLabel?: string
  trueValue?: string
  falseValue?: string
}

export interface SchemaConstraintOperator {
  value: string
  label: string
  /** Number of value inputs the operator needs: 0, 1 or 2. */
  values: number
}

export interface SchemaConstraint {
  key: string
  type: 'text' | 'number' | 'boolean' | 'date' | (string & {})
  label: string
  icon?: string
  operators: SchemaConstraintOperator[]
}

/** One user-composed condition. */
export interface QueryCondition {
  key: string
  operator: string
  value?: string
  value2?: string
}

export interface TableSchema {
  columns: SchemaColumn[]
  filters?: SchemaFilter[]
  /** Groupings the user can switch between, as select options. */
  groups?: Array<{ value: string; label: string }>
  /** Fields the user may build ad-hoc conditions against. */
  constraints?: SchemaConstraint[]
  /** URL template with an `{id}` placeholder, e.g. `/panel/organizations/{id}`. */
  recordUrl?: string | null
  emptyStateTitle?: string | null
  emptyStateDescription?: string | null
  searchable?: boolean
  /** Row selection enabled — the checkbox column. */
  bulkActions?: boolean
  /** Actions offered on each row; the page's `#actions` slot still wins. */
  actions?: SchemaRowAction[]
  /** Actions offered on a selection; the page's `#bulkActions` slot wins. */
  bulkActionItems?: SchemaBulkAction[]
  stickyHeader?: boolean
}

/**
 * Read a possibly-nested field, e.g. `getPath(row, 'organization.name')`.
 *
 * Returns undefined rather than throwing when any segment is missing, so a
 * null relation renders the column's placeholder instead of blowing up.
 */
export function getPath(record: Record<string, any>, path: string): unknown {
  return path
    .split('.')
    .reduce<any>((value, segment) => (value == null ? undefined : value[segment]), record)
}

/**
 * The cell actions that apply to one row, after `hiddenWhen` is resolved
 * against it. Dot-paths are supported, matching every other field reference.
 */
/** One action offered on a row, from `schema.actions`. */
export interface SchemaRowAction {
  name: string
  /** 'drawer' | 'visit' | 'delete' | 'handler' — what the table does with it. */
  behaviour: string
  label: string
  icon?: string
  color?: string
  urlTemplate?: string
  /** Where to ask what deleting would do; `delete` behaviour only. */
  previewUrl?: string
  /** The delete is reversible, so the dialog must not say "cannot be undone". */
  soft?: boolean
  hiddenWhen?: string
  visibleWhen?: string
  confirm?: boolean
  confirmMessage?: string
}

/** One action offered on a selection, from `schema.bulkActionItems`. */
export interface SchemaBulkAction {
  name: string
  /** 'post' | 'handler'. */
  behaviour: string
  label: string
  icon?: string
  color?: string
  variant?: string
  url?: string
  confirm?: boolean
  confirmMessage?: string
}

/** The actions this row offers, after its own visibility fields are read. */
export function visibleRowActions(
  actions: SchemaRowAction[] | undefined,
  record: Record<string, any>,
): SchemaRowAction[] {
  return (actions ?? []).filter((action) => {
    if (action.hiddenWhen && getPath(record, action.hiddenWhen)) return false
    if (action.visibleWhen && !getPath(record, action.visibleWhen)) return false

    return true
  })
}

export function visibleCellActions(
  column: SchemaColumn,
  record: Record<string, any>,
): SchemaColumnAction[] {
  return (column.actions ?? []).filter(
    (action) => !action.hiddenWhen || !getPath(record, action.hiddenWhen),
  )
}

/**
 * Fill a URL template from a record: `/panel/contacts/{id}/organization/{organization.id}`.
 *
 * Returns null when a placeholder cannot be resolved — a link containing a
 * literal `{organization.id}` would 404, so no link is the better answer.
 */
export function resolveRecordUrl(
  template: string | null | undefined,
  record: Record<string, any>,
): string | null {
  if (!template) return null

  let resolved = true

  const url = template.replace(/\{([\w.]+)\}/g, (_match, path: string) => {
    const value = getPath(record, path)

    if (value == null) {
      resolved = false
      return ''
    }

    return String(value)
  })

  return resolved ? url : null
}

/**
 * Empty form value per filter type — the shape useListFilters needs so it can
 * tell "unset" from "set", and so reset() restores the right type.
 */
export function emptyFilterValue(filter: SchemaFilter): unknown {
  if (filter.type === 'dateRange') return { start: null, end: null }
  if (filter.type === 'multiSelect') return []

  return ''
}

/**
 * Build the `defaults` map useListFilters expects, straight from the schema.
 *
 * Keeps the page from restating filter keys the server already declared.
 */
export function filterDefaults(schema: TableSchema | undefined): Record<string, unknown> {
  const defaults = Object.fromEntries(
    (schema?.filters ?? []).map((filter) => [filter.key, emptyFilterValue(filter)]),
  )

  // Grouping and ad-hoc conditions ride the same query-param plumbing.
  if ((schema?.groups ?? []).length > 0) defaults.group = ''
  if ((schema?.constraints ?? []).length > 0) defaults.constraints = []

  return defaults
}

/**
 * Which columns a listing starts with visible.
 *
 * Every key except the ones the schema marks `hiddenByDefault` — the flag was
 * declarable server-side but nothing acted on it, because Table only filters
 * against the list a page hands it.
 */
export function visibleColumnDefaults(schema: TableSchema | undefined): string[] {
  return (schema?.columns ?? [])
    .filter((column) => !column.hiddenByDefault)
    .map((column) => column.key)
}

/** Treat null, undefined and '' as "no value" so the placeholder shows. */
export function isEmptyValue(value: unknown): boolean {
  return value === null || value === undefined || value === ''
}
