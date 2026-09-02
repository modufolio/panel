import { describe, it, expect } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { SchemaTable, type TableSchema } from '../src/index'

/**
 * Child tables: a schema that declares children turns a row expandable, and
 * the expanded row shows one nested table per child, read from the parent
 * row under the child's source. Nothing is fetched.
 */
const castChild = {
  key: 'cast',
  label: 'Cast',
  relation: 'cast',
  source: 'cast',
  columns: [
    { key: 'actor', name: 'actor', label: 'Actor', type: 'text', sortable: false, linksToRecord: true },
    { key: 'character', name: 'character', label: 'Character', type: 'text', sortable: false },
  ],
  recordUrl: '/panel/movies/{parent}/cast/{id}',
  empty: 'No cast listed.',
}

const schemaWithChildren = {
  columns: [{ key: 'title', name: 'title', label: 'Title', type: 'text', sortable: false }],
  children: [castChild],
} as unknown as TableSchema

const schemaWithoutChildren = {
  columns: [{ key: 'title', name: 'title', label: 'Title', type: 'text', sortable: false }],
} as unknown as TableSchema

const records = () => [
  {
    id: 7,
    title: 'Heat',
    cast: [
      { id: 42, actor: 'Al Pacino', character: 'Vincent Hanna' },
      { id: 43, actor: 'Robert De Niro', character: 'Neil McCauley' },
    ],
  },
  { id: 8, title: 'Collateral', cast: [] },
  { id: 9, title: 'Untitled' },
]

const mountTable = (schema: TableSchema, attachTo?: HTMLElement) =>
  mount(SchemaTable, {
    props: { schema, records: records(), filterValues: {} },
    attachTo,
  })

async function expandRow(wrapper: ReturnType<typeof mountTable>, index: number) {
  await wrapper.findAll('.ui-table-expand-btn')[index]!.trigger('click')
  await flushPromises()
}

describe('SchemaTable child tables', () => {
  it('is not expandable without declared children', () => {
    const wrapper = mountTable(schemaWithoutChildren)

    expect(wrapper.find('.ui-table-expand-btn').exists()).toBe(false)
    expect(wrapper.findAll('thead th').map((th) => th.text())).not.toContain('Expand')
  })

  it('renders one nested table per child, with its rows in order', async () => {
    const wrapper = mountTable(schemaWithChildren)

    expect(wrapper.findAll('.ui-table-expand-btn')).toHaveLength(3)
    expect(wrapper.find('.ui-table-nested').exists()).toBe(false)

    await expandRow(wrapper, 0)

    const nested = wrapper.findAll('.ui-table-nested')
    expect(nested).toHaveLength(1)
    expect(wrapper.find('.ui-child-table h4').text()).toBe('Cast')
    expect(wrapper.find('.ui-child-table-count').text()).toBe('2')

    const rows = nested[0]!.findAll('tbody tr.ui-table-row')
    expect(rows.map((row) => row.text())).toEqual(['Al PacinoVincent Hanna', 'Robert De NiroNeil McCauley'])
  })

  it('says what the child says when the parent has no rows, and when it has no key at all', async () => {
    const wrapper = mountTable(schemaWithChildren)

    await expandRow(wrapper, 1)
    expect(wrapper.find('.ui-table-nested .ui-table-empty-nested').text()).toBe('No cast listed.')
    expect(wrapper.find('.ui-child-table-count').text()).toBe('0')

    await expandRow(wrapper, 2)
    expect(wrapper.findAll('.ui-table-nested .ui-table-empty-nested')).toHaveLength(2)
  })

  it('links a child row through its template, with the parent id substituted', async () => {
    const wrapper = mountTable(schemaWithChildren)

    await expandRow(wrapper, 0)

    const links = wrapper.findAll('.ui-table-nested a.ui-drawer-link')
    expect(links.map((a) => a.attributes('href'))).toEqual([
      '/panel/movies/7/cast/42',
      '/panel/movies/7/cast/43',
    ])
    expect(links[0]!.text()).toBe('Al Pacino')
  })

  /**
   * An Inertia reload replaces every row object. Expansion keyed on identity
   * collapsed everything on each save; keyed on the row's id it survives.
   */
  it('keeps a row expanded across a reload that replaces the row objects', async () => {
    const wrapper = mountTable(schemaWithChildren)

    await expandRow(wrapper, 0)
    expect(wrapper.findAll('.ui-table-nested')).toHaveLength(1)

    await wrapper.setProps({ records: structuredClone(records()) })
    await flushPromises()

    expect(wrapper.findAll('.ui-table-nested')).toHaveLength(1)
    expect(wrapper.find('.ui-table-expanded-row').exists()).toBe(true)
  })

  /**
   * Arrow keys on the outer table walk the outer rows only. Before the row
   * query was scoped, a nested table's rows were counted as the parent's.
   */
  it('keeps keyboard row focus on the outer table when a child is expanded', async () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const wrapper = mountTable(schemaWithChildren, host)

    await expandRow(wrapper, 0)

    const outerRows = wrapper
      .findAll('tbody tr.ui-table-row')
      .filter((row) => !row.element.closest('.ui-table-nested'))
    expect(outerRows).toHaveLength(3)

    ;(outerRows[0]!.element as HTMLElement).focus()
    await outerRows[0]!.trigger('focus')
    await wrapper.find('table').trigger('keydown', { key: 'ArrowDown' })

    expect(document.activeElement).toBe(outerRows[1]!.element)
    expect(document.activeElement?.closest('.ui-table-nested')).toBeNull()

    // A keydown inside the nested table stays there.
    const nestedRow = wrapper.find('.ui-table-nested tbody tr.ui-table-row')
    ;(nestedRow.element as HTMLElement).focus()
    await nestedRow.trigger('focus')
    await wrapper.find('.ui-table-nested table').trigger('keydown', { key: 'ArrowDown' })

    expect(document.activeElement?.closest('.ui-table-nested')).not.toBeNull()

    wrapper.unmount()
    host.remove()
  })

  it('lets a page keep its own expanded row', async () => {
    const wrapper = mount(SchemaTable, {
      props: { schema: schemaWithChildren, records: records(), filterValues: {} },
      slots: { expandedRow: '<p class="custom">mine</p>' },
    })

    await expandRow(wrapper, 0)

    expect(wrapper.find('.custom').text()).toBe('mine')
    expect(wrapper.find('.ui-table-nested').exists()).toBe(false)
  })
})
