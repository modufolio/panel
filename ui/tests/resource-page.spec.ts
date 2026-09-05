import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

const visit = vi.fn()
const get = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit, get, reload: vi.fn(), delete: vi.fn() } }))

const { default: ResourcePage } = await import('../src/Components/Resource/ResourcePage.vue')
const { default: ViewSwitcher } = await import('../src/Components/Board/ViewSwitcher.vue')

type TableSchema = import('../src/Components/Table/tableSchema').TableSchema
type ResourceMeta = import('../src/Composables/useResourceListing').ResourceMeta
type ResourceViewOption = import('../src/Components/Board/boardTypes').ResourceViewOption

/**
 * The generic listing, from the props ResourceListing sends. A host page hands
 * them straight through, rows included under the resource's own key, and
 * writes nothing but the chrome around this.
 */
describe('ResourcePage', () => {
  beforeEach(() => {
    visit.mockClear()
    get.mockClear()
  })

  const table = {
    columns: [{ key: 'title', label: 'Title', type: 'text', linksToRecord: true }],
    filters: [],
    recordUrl: '/movies/{id}',
    actions: [],
  } as unknown as TableSchema

  const tableView: ResourceViewOption = { key: 'table', label: 'Table', icon: 'table', type: 'table' }
  const boardView: ResourceViewOption = { key: 'board', label: 'Board', icon: 'kanban', type: 'board' }

  const resource: ResourceMeta = {
    key: 'movies',
    baseUrl: '/movies',
    drawerType: 'movie',
    canCreate: true,
    views: [tableView],
    view: 'table',
  }

  const movies = {
    data: [{ id: 'a', title: 'Heat' }, { id: 'b', title: 'Jaws' }],
    meta: { current_page: 1, last_page: 1, total: 2, from: 1, to: 2, per_page: 25 },
  }

  function render(overrides: Record<string, unknown> = {}, slots: Record<string, (props: unknown) => string> = {}) {
    return mount(ResourcePage, {
      props: { resource, table, stack: [], filters: {} },
      // The rows travel under the resource's key, as an attr, exactly as the
      // server's props arrive.
      attrs: { movies, ...overrides },
      slots,
      global: { stubs: { teleport: true } },
    })
  }

  it('renders the rows the server sent under the resource key, with the humanised title', async () => {
    const wrapper = render()

    await vi.waitFor(() => {
      expect(wrapper.text()).toContain('Heat')
      expect(wrapper.text()).toContain('Jaws')
    })
    expect(wrapper.find('h1, h2').text()).toContain('Movies')
  })

  it('offers a create action only when the server says the viewer may create', async () => {
    expect(render().text()).toContain('New Movie')

    const readOnly = mount(ResourcePage, {
      props: { resource: { ...resource, canCreate: false }, table, stack: [], filters: {} },
      attrs: { movies },
      global: { stubs: { teleport: true } },
    })
    expect(readOnly.text()).not.toContain('New Movie')
  })

  it('shows a view switcher only when there is a choice', () => {
    expect(render().findComponent(ViewSwitcher).exists()).toBe(false)

    const withBoard = mount(ResourcePage, {
      props: {
        resource: { ...resource, views: [tableView, boardView] },
        table,
        stack: [],
        filters: {},
      },
      attrs: { movies },
      global: { stubs: { teleport: true } },
    })
    expect(withBoard.findComponent(ViewSwitcher).exists()).toBe(true)
  })

  it('lets a cell slot replace one generated cell', async () => {
    const wrapper = render({}, {
      'cell-title': (props: unknown) => `★ ${(props as { record: { title: string } }).record.title}`,
    })

    await vi.waitFor(() => {
      expect(wrapper.text()).toContain('★ Heat')
    })
  })
})
