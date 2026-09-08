import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn(), prefetch: vi.fn(), flushAll: vi.fn() } }))

const { default: DrawerRecordFrame } = await import('../src/Components/Drawer/DrawerRecordFrame.vue')

/**
 * A relation tab that declares columns renders its rows as a table with a
 * header row, cells by column type, so the related records read at a glance.
 * One that declares none keeps the two-line list.
 */
describe('Drawer relation table', () => {
  const tasks = [
    { id: 1, title: 'Charge batteries', completed: false, created_at: '2026-09-01T10:00:00+00:00' },
    { id: 2, title: 'Send the shot list', completed: true, created_at: '2026-09-02T10:00:00+00:00' },
  ]

  const columns = [
    { key: 'title', name: 'title', label: 'Title', type: 'text', sortable: false },
    { key: 'completed', name: 'completed', label: 'Done', type: 'boolean', sortable: false },
  ]

  it('renders headers and one row per related record', () => {
    const wrapper = mount(DrawerRecordFrame, {
      props: {
        activeTab: 'tasks',
        frame: {
          type: 'issue',
          data: { id: 7, title: 'Wedding', tasks },
          tabs: [{ key: 'tasks', label: 'Sub-tasks', type: 'relation', source: 'tasks', columns, badge: 2 }],
        },
      },
    })

    const table = wrapper.find('.ui-drawer-relation-table')
    expect(table.exists()).toBe(true)
    expect(table.findAll('th').map((th) => th.text().trim())).toEqual(['Title', 'Done'])
    expect(table.findAll('tbody tr')).toHaveLength(2)
    expect(table.text()).toContain('Charge batteries')
    expect(table.text()).toContain('Send the shot list')
    expect(wrapper.find('.ui-drawer-relation-list').exists()).toBe(false)
  })

  it('keeps the two-line list when no columns are declared', () => {
    const wrapper = mount(DrawerRecordFrame, {
      props: {
        activeTab: 'tasks',
        frame: {
          type: 'issue',
          data: { id: 7, tasks },
          tabs: [{ key: 'tasks', label: 'Sub-tasks', type: 'relation', source: 'tasks', primary: 'title', columns: null }],
        },
      },
    })

    expect(wrapper.find('.ui-drawer-relation-table').exists()).toBe(false)
    expect(wrapper.find('.ui-drawer-relation-list').exists()).toBe(true)
    expect(wrapper.text()).toContain('Charge batteries')
  })

  it('shows the empty text instead of an empty table', () => {
    const wrapper = mount(DrawerRecordFrame, {
      props: {
        activeTab: 'tasks',
        frame: {
          type: 'issue',
          data: { id: 7, tasks: [] },
          tabs: [{ key: 'tasks', label: 'Sub-tasks', type: 'relation', source: 'tasks', columns, empty: 'No sub-tasks yet.' }],
        },
      },
    })

    expect(wrapper.text()).toContain('No sub-tasks yet.')
    expect(wrapper.find('th').exists()).toBe(false)
  })
})
