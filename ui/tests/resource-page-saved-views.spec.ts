import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

const get = vi.fn()
vi.mock('@inertiajs/vue3', () => ({
  router: { prefetch: vi.fn(), flushAll: vi.fn(), visit: vi.fn(), get, reload: vi.fn(), delete: vi.fn(), patch: vi.fn() },
}))

const { default: ResourcePage } = await import('../src/Components/Resource/ResourcePage.vue')
const { default: SavedViews } = await import('../src/Components/Resource/SavedViews.vue')
const { loadSavedViews } = await import('../src/Composables/savedViews')

type TableSchema = import('../src/Components/Table/tableSchema').TableSchema
type ResourceMeta = import('../src/Composables/useResourceListing').ResourceMeta

/**
 * A view is only ever a URL someone gave a name: applying one writes the
 * filter form, and the form is what the visit is made of.
 */
describe('ResourcePage saved views', () => {
  const table = {
    columns: [
      { key: 'title', name: 'title', label: 'Title', type: 'text', sortable: true, toggleable: true },
      { key: 'venue', name: 'venue', label: 'Venue', type: 'text', sortable: true, toggleable: true },
    ],
    filters: [{ key: 'status', type: 'select', label: 'Status', options: [{ label: 'Open', value: 'open' }] }],
    actions: [],
  } as unknown as TableSchema

  const resource: ResourceMeta = {
    key: 'screenings',
    baseUrl: '/panel/screenings',
    drawerType: 'screening',
    title: 'Screenings',
    label: 'Screening',
  }

  const screenings = { data: [], meta: { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 25 } }

  function render() {
    return mount(ResourcePage, {
      props: { resource, table, stack: [], filters: {} },
      attrs: { screenings },
      global: { stubs: { teleport: true } },
    })
  }

  /**
   * The picker, scoped: the page has a search box and a column toggle of its
   * own, so an unscoped `find('input')` types into the wrong one — and the
   * trigger is named after the active view, so it cannot be found by label.
   */
  async function openPicker(wrapper: ReturnType<typeof render>) {
    const picker = wrapper.findComponent(SavedViews)

    await picker.find('button').trigger('click')

    return picker
  }

  beforeEach(() => {
    localStorage.clear()
    get.mockClear()
  })

  it('saves what the list is showing, under this resource, in the browser', async () => {
    localStorage.setItem('panel.views.screenings', JSON.stringify([]))

    const wrapper = render()
    const picker = await openPicker(wrapper)

    await picker.find('input[type="text"]').setValue('Open ones')
    await picker.find('form').trigger('submit')

    const [saved] = loadSavedViews('screenings')
    expect(saved.name).toBe('Open ones')
    // Columns ride along: which columns are on is part of how a list looks.
    expect(saved.columns).toEqual(['title', 'venue'])
  })

  it('applying one visits the list with the view\'s query', async () => {
    localStorage.setItem('panel.views.screenings', JSON.stringify([
      { name: 'Open ones', filters: { status: 'open', sort: '-starts_on' }, columns: ['title'] },
    ]))

    const wrapper = render()
    const picker = await openPicker(wrapper)
    await picker.findAll('button').filter((button) => button.text() === 'Open ones').at(-1)?.trigger('click')

    await vi.waitFor(() => expect(get).toHaveBeenCalled(), { timeout: 1000 })

    const [url, params] = get.mock.calls.at(-1) as [string, Record<string, unknown>]
    expect(url).toBe('/panel/screenings')
    expect(params).toMatchObject({ status: 'open', sort: '-starts_on' })
  })

  it('applying one restores the columns it was saved with', async () => {
    localStorage.setItem('panel.views.screenings', JSON.stringify([
      { name: 'Titles only', filters: {}, columns: ['title'] },
    ]))

    const wrapper = render()
    const picker = await openPicker(wrapper)
    // The trigger is named after the active view, so the row is the *last*
    // button carrying that name, not the first.
    await picker.findAll('button').filter((button) => button.text() === 'Titles only').at(-1)?.trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.findAll('th').map((th) => th.text())).not.toContain('Venue')
  })

  it('forgets a deleted view', async () => {
    localStorage.setItem('panel.views.screenings', JSON.stringify([
      { name: 'Open ones', filters: { status: 'open' } },
    ]))

    const wrapper = render()
    const picker = await openPicker(wrapper)
    await picker.find('[aria-label="Delete Open ones"]').trigger('click')

    expect(loadSavedViews('screenings')).toEqual([])
  })
})
