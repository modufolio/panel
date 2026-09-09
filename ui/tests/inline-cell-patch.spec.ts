import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

const patch = vi.fn()
vi.mock('@inertiajs/vue3', () => ({
  router: { prefetch: vi.fn(), flushAll: vi.fn(), visit: vi.fn(), get: vi.fn(), reload: vi.fn(), delete: vi.fn(), patch },
}))

const { default: ResourcePage } = await import('../src/Components/Resource/ResourcePage.vue')

type TableSchema = import('../src/Components/Table/tableSchema').TableSchema
type ResourceMeta = import('../src/Composables/useResourceListing').ResourceMeta

/**
 * A generated listing has no page to supply `cellHandlers`, so an editable
 * column used to render a control that saved nowhere. It now saves through the
 * resource's own `patch` route, keyed by column.
 */
describe('ResourcePage inline cell edits', () => {
  beforeEach(() => patch.mockClear())

  const table = {
    columns: [
      { key: 'title', name: 'title', label: 'Title', type: 'text', sortable: false },
      {
        key: 'published',
        name: 'published',
        label: 'Published',
        type: 'toggleIcon',
        sortable: false,
        editable: true,
      },
    ],
    filters: [],
    actions: [],
  } as unknown as TableSchema

  const resource: ResourceMeta = {
    key: 'screenings',
    baseUrl: '/screenings',
    drawerType: 'screening',
    title: 'Screenings',
    label: 'Screening',
    urls: { patch: '/panel/screenings/{id}' },
  }

  const screenings = {
    data: [{ id: 'a', title: 'Heat', published: false }],
    meta: { current_page: 1, last_page: 1, total: 1, from: 1, to: 1, per_page: 25 },
  }

  function render(overrides: Partial<ResourceMeta> = {}) {
    return mount(ResourcePage, {
      props: { resource: { ...resource, ...overrides }, table, stack: [], filters: {} },
      attrs: { screenings },
      global: { stubs: { teleport: true } },
    })
  }

  it('saves the flipped value to the record, keyed by column', async () => {
    const wrapper = render()

    await vi.waitFor(() => expect(wrapper.find('button.ui-toggle-icon-column').exists()).toBe(true))
    await wrapper.find('button.ui-toggle-icon-column').trigger('click')

    expect(patch).toHaveBeenCalledTimes(1)
    const [url, payload] = patch.mock.calls[0]
    expect(url).toBe('/panel/screenings/a')
    expect(payload).toEqual({ published: true })
  })

  it('carries the list state, because the redirect URL is what Inertia reloads', async () => {
    const wrapper = mount(ResourcePage, {
      props: { resource, table, stack: [], filters: { search: 'heat' } },
      attrs: {
        // Page three of a filtered list: both halves have to survive the
        // round trip, and the page number lives on the rows, not the filters.
        screenings: {
          ...screenings,
          meta: { ...screenings.meta, current_page: 3, per_page: 25 },
        },
      },
      global: { stubs: { teleport: true } },
    })

    await vi.waitFor(() => expect(wrapper.find('button.ui-toggle-icon-column').exists()).toBe(true))
    await wrapper.find('button.ui-toggle-icon-column').trigger('click')

    const url = decodeURIComponent(patch.mock.calls[0][0] as string)
    expect(url).toContain('search=heat')
    expect(url).toContain('page[number]=3')
    expect(url).toContain('page[size]=25')
  })

  it('saves nothing when the resource generates no patch route', async () => {
    const wrapper = render({ urls: {} })

    await vi.waitFor(() => expect(wrapper.find('button.ui-toggle-icon-column').exists()).toBe(true))
    await wrapper.find('button.ui-toggle-icon-column').trigger('click')

    expect(patch).not.toHaveBeenCalled()
  })
})
