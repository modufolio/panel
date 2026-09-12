import { describe, it, expect } from 'vitest'
import {
  changeRowLayout,
  cloneRow,
  createBlock,
  createRow,
  moveBlock,
  moveRow,
  normalizeRows,
  parseLayouts,
  widthToSpan,
  type LayoutRow,
} from '../src/Components/LayoutField/layoutModel'

const block = (type = 'heading', text = 'x') => ({ id: `b-${text}`, type, content: { text } })

function row(columns: { width: string; blocks?: ReturnType<typeof block>[] }[]): LayoutRow {
  return {
    id: 'row',
    columns: columns.map((c, i) => ({ id: `c${i}`, width: c.width, blocks: c.blocks ?? [] })),
  }
}

describe('parseLayouts', () => {
  it('splits each preset string into a width list', () => {
    expect(parseLayouts(['1/1', '1/2 1/2', ' 1/3  1/3 1/3 '])).toEqual([['1/1'], ['1/2', '1/2'], ['1/3', '1/3', '1/3']])
  })

  it('drops entries that are not fractions and falls back to the defaults when nothing is left', () => {
    expect(parseLayouts(['1/2 wide 1/2'])).toEqual([['1/2', '1/2']])
    expect(parseLayouts(['nonsense'])).toEqual(parseLayouts(undefined))
    expect(parseLayouts(undefined).length).toBeGreaterThan(1)
  })
})

describe('widthToSpan', () => {
  it('maps a fraction of the row onto twelve tracks', () => {
    expect(widthToSpan('1/1')).toBe(12)
    expect(widthToSpan('1/2')).toBe(6)
    expect(widthToSpan('1/3')).toBe(4)
    expect(widthToSpan('2/3')).toBe(8)
    expect(widthToSpan('1/4')).toBe(3)
  })

  it('treats anything unreadable as full width', () => {
    expect(widthToSpan('wide')).toBe(12)
    expect(widthToSpan('1/0')).toBe(12)
  })
})

describe('normalizeRows', () => {
  it('keeps row ids and supplies the column and block ids an imported document lacks', () => {
    const rows = normalizeRows([
      { id: 'row-hero', columns: [{ width: '1/1', blocks: [{ type: 'image', content: { media: 'a.jpg' } }] }] },
    ])

    expect(rows).toHaveLength(1)
    expect(rows[0]!.id).toBe('row-hero')
    expect(rows[0]!.columns[0]!.id).toMatch(/\S/)
    expect(rows[0]!.columns[0]!.width).toBe('1/1')
    expect(rows[0]!.columns[0]!.blocks[0]!.id).toMatch(/\S/)
    expect(rows[0]!.columns[0]!.blocks[0]!.content).toEqual({ media: 'a.jpg' })
  })

  it('drops what is not a row, column or typed block instead of guessing', () => {
    const rows = normalizeRows([null, 'text', { columns: [42, { width: 'nope', blocks: [{ content: {} }, { type: 'text' }] }] }])

    expect(rows).toHaveLength(1)
    expect(rows[0]!.columns).toHaveLength(1)
    expect(rows[0]!.columns[0]!.width).toBe('1/1')
    expect(rows[0]!.columns[0]!.blocks.map((b) => b.type)).toEqual(['text'])
  })

  it('returns nothing for a value that is not a list', () => {
    expect(normalizeRows('[]')).toEqual([])
    expect(normalizeRows(undefined)).toEqual([])
  })
})

describe('createRow / createBlock', () => {
  it('builds a row with one empty column per width', () => {
    const r = createRow(['2/3', '1/3'])
    expect(r.columns.map((c) => c.width)).toEqual(['2/3', '1/3'])
    expect(r.columns.every((c) => c.blocks.length === 0)).toBe(true)
  })

  it('starts a block with its type defaults', () => {
    expect(createBlock('heading').content).toEqual({ level: 'h2', text: '' })
    expect(createBlock('quote').content).toEqual({ text: '', citation: '' })
    expect(createBlock('unknown').content).toEqual({})
  })
})

describe('changeRowLayout', () => {
  it('just switches an empty row', () => {
    const out = changeRowLayout(row([{ width: '1/1' }]), ['1/2', '1/2'])
    expect(out).toHaveLength(1)
    expect(out[0]!.id).toBe('row')
    expect(out[0]!.columns.map((c) => c.width)).toEqual(['1/2', '1/2'])
  })

  it('pours filled columns into the new layout in order', () => {
    const a = block('heading', 'a')
    const b = block('text', 'b')
    const out = changeRowLayout(row([{ width: '1/2', blocks: [a] }, { width: '1/2', blocks: [b] }]), ['1/3', '1/3', '1/3'])

    expect(out).toHaveLength(1)
    expect(out[0]!.columns.map((c) => c.blocks.map((x) => x.id))).toEqual([['b-a'], ['b-b'], []])
  })

  it('overflows into further rows when the new layout has fewer columns', () => {
    const [a, b, c] = [block('heading', 'a'), block('text', 'b'), block('quote', 'c')]
    const out = changeRowLayout(
      row([{ width: '1/3', blocks: [a] }, { width: '1/3', blocks: [b] }, { width: '1/3', blocks: [c] }]),
      ['1/1'],
    )

    expect(out).toHaveLength(3)
    expect(out.map((r) => r.columns[0]!.blocks[0]!.id)).toEqual(['b-a', 'b-b', 'b-c'])
    expect(out[0]!.id).toBe('row')
    expect(new Set(out.map((r) => r.id)).size).toBe(3)
  })

  it('skips empty columns so nothing empty is carried over', () => {
    const a = block('heading', 'a')
    const out = changeRowLayout(row([{ width: '1/2' }, { width: '1/2', blocks: [a] }]), ['1/1'])
    expect(out).toHaveLength(1)
    expect(out[0]!.columns[0]!.blocks.map((x) => x.id)).toEqual(['b-a'])
  })
})

describe('cloneRow', () => {
  it('copies content and replaces every id', () => {
    const source = row([{ width: '1/1', blocks: [block('text', 'a')] }])
    const copy = cloneRow(source)

    expect(copy.id).not.toBe(source.id)
    expect(copy.columns[0]!.id).not.toBe(source.columns[0]!.id)
    expect(copy.columns[0]!.blocks[0]!.id).not.toBe(source.columns[0]!.blocks[0]!.id)
    expect(copy.columns[0]!.blocks[0]!.content).toEqual({ text: 'a' })
    // A deep copy: editing the clone leaves the source alone.
    copy.columns[0]!.blocks[0]!.content.text = 'changed'
    expect(source.columns[0]!.blocks[0]!.content.text).toBe('a')
  })
})

describe('moveBlock', () => {
  const rows = (): LayoutRow[] => [
    row([{ width: '1/2', blocks: [block('h', 'a'), block('h', 'b')] }, { width: '1/2', blocks: [block('h', 'c')] }]),
  ]

  it('moves within a column, using the on-screen index', () => {
    const out = moveBlock(rows(), { row: 0, column: 0, block: 0 }, { row: 0, column: 0, block: 2 })
    expect(out[0]!.columns[0]!.blocks.map((b) => b.id)).toEqual(['b-b', 'b-a'])
  })

  it('moves across columns', () => {
    const out = moveBlock(rows(), { row: 0, column: 0, block: 1 }, { row: 0, column: 1, block: 0 })
    expect(out[0]!.columns[0]!.blocks.map((b) => b.id)).toEqual(['b-a'])
    expect(out[0]!.columns[1]!.blocks.map((b) => b.id)).toEqual(['b-b', 'b-c'])
  })

  it('leaves the rows untouched for an address that does not exist', () => {
    const input = rows()
    expect(moveBlock(input, { row: 3, column: 0, block: 0 }, { row: 0, column: 0, block: 0 })).toBe(input)
  })
})

describe('moveRow', () => {
  const three = (): LayoutRow[] => ['a', 'b', 'c'].map((id) => ({ id, columns: [] }))

  it('moves a row to an on-screen index, downwards and upwards', () => {
    expect(moveRow(three(), 0, 3).map((r) => r.id)).toEqual(['b', 'c', 'a'])
    expect(moveRow(three(), 2, 0).map((r) => r.id)).toEqual(['c', 'a', 'b'])
    expect(moveRow(three(), 0, 1).map((r) => r.id)).toEqual(['a', 'b', 'c'])
  })
})
