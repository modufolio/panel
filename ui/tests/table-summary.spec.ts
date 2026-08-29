import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Table from '../src/Components/Table/Table.vue'

/**
 * The summary footer has to line up with the header, cell for cell.
 *
 * A footer that silently renders one cell short puts every aggregate under
 * the wrong column — which reads as a styling glitch, not a bug, so it needs
 * pinning structurally rather than by eye.
 */
const columns = [
  { key: 'name', name: 'name', label: 'Name', sortable: true },
  { key: 'city', name: 'city', label: 'City', sortable: true },
  { key: 'phone', name: 'phone', label: 'Phone', sortable: false },
]

const records = [
  { name: 'Acme', city: 'Berlin', phone: '123' },
  { name: 'Globex', city: 'Paris', phone: '456' },
]

function mountTable(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mount(Table, {
    props: { columns, records, ...props },
    slots: {
      summary: '<template #summary="{ column }">{{ column.key }}</template>',
      ...slots,
    },
  })
}

describe('Table summary footer', () => {
  it('emits one footer cell per header cell when bulk actions are on', () => {
    const wrapper = mountTable({ bulkActionsEnabled: true })

    const headerCells = wrapper.findAll('thead th').length
    const footerCells = wrapper.findAll('tfoot td').length

    expect(footerCells).toBe(headerCells)
  })

  it('aligns each summary cell with its column', () => {
    const wrapper = mountTable({ bulkActionsEnabled: true })

    const footerCells = wrapper.findAll('tfoot td')

    // Leading cell belongs to the bulk-select column and must stay empty.
    expect(footerCells[0].text()).toBe('')

    // The remaining cells carry the column keys, in order.
    expect(footerCells.slice(1).map((cell) => cell.text())).toEqual(['name', 'city', 'phone'])
  })

  it('keeps alignment without bulk actions', () => {
    const wrapper = mountTable({ bulkActionsEnabled: false })

    const footerCells = wrapper.findAll('tfoot td')

    expect(footerCells).toHaveLength(columns.length)
    expect(footerCells.map((cell) => cell.text())).toEqual(['name', 'city', 'phone'])
  })

  it('accounts for the expand column too', () => {
    const wrapper = mountTable({ bulkActionsEnabled: true, expandable: true })

    expect(wrapper.findAll('tfoot td')).toHaveLength(wrapper.findAll('thead th').length)
  })

  it('renders no footer at all without a summary slot', () => {
    const wrapper = mount(Table, { props: { columns, records } })

    expect(wrapper.find('tfoot').exists()).toBe(false)
  })
})
