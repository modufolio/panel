/**
 * A layout is rows; a row is columns with a width fraction; a column is a
 * stack of typed blocks:
 *
 *   [{ id, columns: [{ id, width: '1/3', blocks: [{ id, type, content }] }] }]
 *
 * Everything here is pure: no component state, no DOM. The components own
 * the interaction; this owns the shape and the operations on it.
 */

export interface LayoutBlock {
  id: string
  type: string
  content: Record<string, unknown>
}

export interface LayoutColumn {
  id: string
  width: string
  blocks: LayoutBlock[]
}

export interface LayoutRow {
  id: string
  columns: LayoutColumn[]
  attrs?: Record<string, unknown>
}

export interface BlockType {
  type: string
  label: string
  icon: string
  defaults: () => Record<string, unknown>
}

export const BLOCK_TYPES: readonly BlockType[] = [
  { type: 'heading', label: 'Heading', icon: 'heading-2', defaults: () => ({ level: 'h2', text: '' }) },
  { type: 'text',    label: 'Text',    icon: 'paragraph',  defaults: () => ({ text: '' }) },
  { type: 'quote',   label: 'Quote',   icon: 'blockquote', defaults: () => ({ text: '', citation: '' }) },
  { type: 'image',   label: 'Image',   icon: 'image-block', defaults: () => ({ id: null, media: '', url: '', thumbnail_url: '', alt: '', ratio: '', crop: false }) },
]

export const HEADING_LEVELS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as const

export const IMAGE_RATIOS: readonly { value: string; label: string }[] = [
  { value: '',     label: 'Auto' },
  { value: '1/1',  label: '1:1' },
  { value: '16/9', label: '16:9' },
  { value: '10/8', label: '10:8' },
  { value: '21/9', label: '21:9' },
  { value: '7/5',  label: '7:5' },
  { value: '4/3',  label: '4:3' },
  { value: '5/3',  label: '5:3' },
  { value: '3/2',  label: '3:2' },
  { value: '3/1',  label: '3:1' },
]

export const DEFAULT_LAYOUTS = ['1/1', '1/2 1/2', '1/3 1/3 1/3', '2/3 1/3', '1/3 2/3', '1/4 1/4 1/4 1/4']

export function blockType(type: string): BlockType | undefined {
  return BLOCK_TYPES.find((t) => t.type === type)
}

export function uuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
  })
}

export function parseLayouts(layouts: readonly string[] | undefined): string[][] {
  const parsed = (layouts ?? DEFAULT_LAYOUTS)
    .map((preset) => preset.trim().split(/\s+/).filter((w) => isWidth(w)))
    .filter((columns) => columns.length > 0)

  return parsed.length > 0 ? parsed : parseLayouts(DEFAULT_LAYOUTS)
}

export function isWidth(value: unknown): value is string {
  return typeof value === 'string' && /^\d+\/\d+$/.test(value)
}

export function widthToSpan(width: string): number {
  const m = /^(\d+)\/(\d+)$/.exec(width)
  if (!m) return 12
  const den = Number(m[2])
  if (den <= 0) return 12
  return Math.max(1, Math.min(12, Math.round((12 * Number(m[1])) / den)))
}

export function createBlock(type: string): LayoutBlock {
  const def = blockType(type)
  return { id: uuid(), type, content: def ? def.defaults() : {} }
}

export function createColumn(width: string, blocks: LayoutBlock[] = []): LayoutColumn {
  return { id: uuid(), width, blocks }
}

export function createRow(columns: readonly string[]): LayoutRow {
  return { id: uuid(), columns: columns.map((width) => createColumn(width)) }
}

export function rowWidths(row: LayoutRow): string[] {
  return row.columns.map((c) => c.width)
}

export function sameLayout(a: readonly string[], b: readonly string[]): boolean {
  return a.length === b.length && a.every((w, i) => w === b[i])
}

export function normalizeRows(value: unknown): LayoutRow[] {
  if (!Array.isArray(value)) return []

  const rows: LayoutRow[] = []
  for (const raw of value) {
    if (!raw || typeof raw !== 'object') continue
    const row = raw as Partial<LayoutRow>
    const columns: unknown[] = Array.isArray(row.columns) ? row.columns : []

    rows.push({
      id: typeof row.id === 'string' && row.id !== '' ? row.id : uuid(),
      ...(row.attrs && typeof row.attrs === 'object' ? { attrs: row.attrs } : {}),
      columns: columns
        .filter((c): c is Partial<LayoutColumn> => !!c && typeof c === 'object')
        .map((c) => ({
          id: typeof c.id === 'string' && c.id !== '' ? c.id : uuid(),
          width: isWidth(c.width) ? c.width : '1/1',
          blocks: (Array.isArray(c.blocks) ? (c.blocks as unknown[]) : [])
            .filter((b): b is Partial<LayoutBlock> => !!b && typeof b === 'object' && typeof (b as Partial<LayoutBlock>).type === 'string')
            .map((b) => ({
              id: typeof b.id === 'string' && b.id !== '' ? b.id : uuid(),
              type: b.type as string,
              content: b.content && typeof b.content === 'object' ? { ...(b.content as Record<string, unknown>) } : {},
            })),
        })),
    })
  }

  return rows
}

export function cloneRow(row: LayoutRow): LayoutRow {
  return {
    ...row,
    id: uuid(),
    columns: row.columns.map((column) => ({
      ...column,
      id: uuid(),
      blocks: column.blocks.map(cloneBlock),
    })),
  }
}

export function cloneBlock(block: LayoutBlock): LayoutBlock {
  return { ...block, id: uuid(), content: JSON.parse(JSON.stringify(block.content)) }
}

export function changeRowLayout(row: LayoutRow, columns: readonly string[]): LayoutRow[] {
  const filled = row.columns.filter((c) => c.blocks.length > 0)

  if (filled.length === 0) {
    return [{ ...row, columns: columns.map((width) => createColumn(width)) }]
  }

  const perRow = columns.length
  const rows: LayoutRow[] = []

  for (let offset = 0; offset < filled.length; offset += perRow) {
    const chunk = filled.slice(offset, offset + perRow)
    rows.push({
      ...(offset === 0 ? row : { ...row, id: uuid() }),
      columns: columns.map((width, i) => createColumn(width, chunk[i]?.blocks ?? [])),
    })
  }

  return rows
}

export function toValue(rows: LayoutRow[]): LayoutRow[] {
  return JSON.parse(JSON.stringify(rows))
}

export interface BlockAddress {
  row: number
  column: number
  block: number
}
export function moveBlock(rows: LayoutRow[], from: BlockAddress, to: BlockAddress): LayoutRow[] {
  const next = toValue(rows)
  const source = next[from.row]?.columns[from.column]
  const target = next[to.row]?.columns[to.column]
  if (!source || !target) return rows

  const [block] = source.blocks.splice(from.block, 1)
  if (!block) return rows

  let index = to.block
  if (source === target && from.block < to.block) index -= 1
  target.blocks.splice(Math.max(0, Math.min(index, target.blocks.length)), 0, block)

  return next
}

export function moveRow(rows: LayoutRow[], from: number, to: number): LayoutRow[] {
  if (from === to || from < 0 || from >= rows.length) return rows
  const next = [...rows]
  const [row] = next.splice(from, 1)
  if (!row) return rows
  const index = from < to ? to - 1 : to
  next.splice(Math.max(0, Math.min(index, next.length)), 0, row)
  return next
}
