import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { prefetch: vi.fn(), flushAll: vi.fn(), visit, get: vi.fn(), reload: vi.fn(), delete: vi.fn() } }))

const { default: ResourcePage } = await import('../src/Components/Resource/ResourcePage.vue')

type TableSchema = import('../src/Components/Table/tableSchema').TableSchema
type ResourceMeta = import('../src/Composables/useResourceListing').ResourceMeta

/**
 * The panel that adds a row to a list is the next panel in the stack, so it
 * behaves like one: its scrim dismisses it, and its close button sits where
 * every drawer's does.
 */
describe('ResourcePage add panel', () => {
  const resource = {
    key: 'movies',
    baseUrl: '/movies',
    drawerType: 'movie',
    canEdit: true,
    views: [{ key: 'table', label: 'Table', icon: 'table', type: 'table' }],
    view: 'table',
  } as unknown as ResourceMeta

  const table = { columns: [{ key: 'title', label: 'Title', type: 'text' }], filters: [], actions: [] } as unknown as TableSchema

  const stack = [{
    type: 'movie',
    title: 'Arrival',
    data: { id: 'm1', title: 'Arrival', cast: [] },
    tabs: [{
      key: 'cast',
      label: 'Cast',
      type: 'relation' as const,
      source: 'cast',
      addable: true,
      addLabel: '+ Add',
      addUrl: '/movies/m1/relations/cast',
      addFields: [{ key: 'character', type: 'text', label: 'Character' }],
    }],
  }]

  async function openAddPanel() {
    const wrapper = mount(ResourcePage, {
      attachTo: document.body,
      props: { resource, table, stack, filters: {} },
      attrs: { movies: { data: [], meta: { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 25 } } },
    })
    await flushPromises()

    const add = Array.from(document.querySelectorAll('button')).find((el) => el.textContent?.includes('+ Add'))
    add?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flushPromises()

    return wrapper
  }

  it('puts the whole stack away when the dimmed page behind it is pressed', async () => {
    document.body.innerHTML = ''
    visit.mockClear()
    const wrapper = await openAddPanel()

    const panel = document.querySelector('[data-testid="add-panel"]')
    expect(panel).toBeTruthy()

    // Exempt from the inert pass the panel's layer applies to its siblings —
    // an inert scrim swallows the very press it exists to receive.
    const scrim = document.querySelector('[data-testid="add-panel-scrim"]') as HTMLElement
    expect(scrim).toBeTruthy()
    expect(scrim.inert).toBe(false)

    scrim.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flushPromises()

    expect(document.querySelector('[data-testid="add-panel"]')).toBeNull()

    // …and the drawers under it, in the same press: the dimmed page is what
    // is left of the listing, so pressing it means "back to that".
    expect(visit).toHaveBeenCalledTimes(1)
    expect(visit.mock.calls[0][0]).toBe('/movies')

    wrapper.unmount()
  })

  it('puts its close button before the title, as a drawer does', async () => {
    document.body.innerHTML = ''
    const wrapper = await openAddPanel()

    const header = document.querySelector('[data-testid="add-panel"] > div') as HTMLElement
    const [first] = Array.from(header.children)

    expect(first.tagName).toBe('BUTTON')
    expect(first.getAttribute('aria-label')).toBe('Close')

    wrapper.unmount()
  })
})
