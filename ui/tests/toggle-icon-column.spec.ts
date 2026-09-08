import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SchemaCell from '../src/Components/Table/SchemaCell'
import type { SchemaColumn } from '../src/index'

function column(overrides: Partial<SchemaColumn> = {}): SchemaColumn {
  return {
    key: 'featured',
    name: 'featured',
    label: 'Featured',
    type: 'toggleIcon',
    sortable: false,
    editable: true,
    ...overrides,
  } as SchemaColumn
}

const record = { id: '1', featured: false }

describe('a toggleIcon cell', () => {
  it('saves the flipped value through the column handler', async () => {
    const handler = vi.fn()

    const cell = mount(SchemaCell, {
      props: { column: column(), record, value: false, handler },
    })

    await cell.find('button').trigger('click')

    expect(handler).toHaveBeenCalledWith(record, 'featured', true)
  })

  it('renders the off state, and its icon, until the value says otherwise', () => {
    const off = mount(SchemaCell, {
      props: { column: column({ offIcon: 'x', offColor: 'danger' }), record, value: false },
    })

    expect(off.find('button').attributes('aria-pressed')).toBe('false')
    expect(off.find('button').classes()).toContain('text-danger-600')

    const on = mount(SchemaCell, {
      props: { column: column({ onColor: 'warning' }), record, value: true },
    })

    expect(on.find('button').attributes('aria-pressed')).toBe('true')
    expect(on.find('button').classes()).toContain('text-warning-600')
  })

  it('still renders the control when the row has no value yet', () => {
    // `null` on a never-set flag is empty, but the cell is the control you
    // click — a dash there would leave the flag unsettable.
    const cell = mount(SchemaCell, {
      props: { column: column(), record: { id: '1' }, value: null },
    })

    expect(cell.find('button').exists()).toBe(true)
  })

  it('drops the button for a row the column declares read-only', () => {
    const cell = mount(SchemaCell, {
      props: {
        column: column({ readOnlyWhen: 'deleted_at' }),
        record: { ...record, deleted_at: '2026-01-01' },
        value: true,
      },
    })

    expect(cell.find('button').exists()).toBe(false)
    expect(cell.find('span.ui-toggle-icon-column').exists()).toBe(true)
  })

  it('keeps a disabled row inert while still showing its state', async () => {
    const handler = vi.fn()

    const cell = mount(SchemaCell, {
      props: {
        column: column({ disabledWhen: 'deleted_at' }),
        record: { ...record, deleted_at: '2026-01-01' },
        value: true,
        handler,
      },
    })

    expect(cell.find('button').attributes('disabled')).toBeDefined()

    await cell.find('button').trigger('click')

    expect(handler).not.toHaveBeenCalled()
  })
})
