import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Table from '../src/Components/Table/Table.vue'

const columns = [
  { key: 'title', name: 'title', label: 'Title', sortable: true },
  { key: 'year', name: 'year', label: 'Year', sortable: true },
]
const records = [{ id: 1, title: 'Heat', year: 1995 }]

/** A header click walks ascending → descending → unsorted. */
describe('sort cycle', () => {
  const click = async (sortColumn?: string, sortDirection?: 'asc' | 'desc') => {
    const wrapper = mount(Table, {
      props: { columns, records, sortColumn, sortDirection, searchable: false },
    })
    const button = wrapper.findAll('.ui-table-sort-btn')[0]
    await button.trigger('click')

    return wrapper.emitted('sort')?.[0]?.[0]
  }

  it('starts ascending on a column not sorted yet', async () => {
    expect(await click()).toEqual({ column: 'title', direction: 'asc' })
    expect(await click('year', 'asc')).toEqual({ column: 'title', direction: 'asc' })
  })

  it('turns descending, then lets go', async () => {
    expect(await click('title', 'asc')).toEqual({ column: 'title', direction: 'desc' })
    expect(await click('title', 'desc')).toEqual({ column: 'title', direction: null })
  })
})
