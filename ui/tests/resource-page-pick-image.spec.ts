import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const visit = vi.fn()
const get = vi.fn()
const reload = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { prefetch: vi.fn(), flushAll: vi.fn(), visit, get, reload, delete: vi.fn() } }))

const { default: ResourcePage } = await import('../src/Components/Resource/ResourcePage.vue')
const { default: MediaPickerDialog } = await import('../src/Components/Media/MediaPickerDialog.vue')

type TableSchema = import('../src/Components/Table/tableSchema').TableSchema
type ResourceMeta = import('../src/Composables/useResourceListing').ResourceMeta
type StackItem = import('../src/Components/Drawer/useDrawerStack').StackItem

/**
 * Picking a cover for a `pickable` field bubbles from the field grid up
 * through the frame and the stack to this page, which opens the media
 * dialog, posts the chosen image to the field's stamped `pickUrl` on
 * selection, and — same reasoning as the add-a-row flow — re-reads the
 * frame afterwards rather than patching a second copy of it here.
 */
describe('ResourcePage picking an image for a drawer field', () => {
  const table = {
    columns: [{ key: 'title', label: 'Title', type: 'text', linksToRecord: true }],
    filters: [],
    recordUrl: '/movies/{id}',
    actions: [],
  } as unknown as TableSchema

  const resource: ResourceMeta = {
    key: 'movies',
    baseUrl: '/movies',
    drawerType: 'movie',
    title: 'Movies',
    label: 'Movie',
    canCreate: true,
    views: [{ key: 'table', label: 'Table', icon: 'table', type: 'table' }],
    view: 'table',
  }

  const movies = {
    data: [{ id: 'm1', title: 'Arrival' }],
    meta: { current_page: 1, last_page: 1, total: 1, from: 1, to: 1, per_page: 25 },
  }

  const stack: StackItem[] = [
    {
      type: 'movie',
      title: 'Arrival',
      data: { id: 'm1', title: 'Arrival', cover: null },
      tabs: [
        {
          key: 'details',
          label: 'Details',
          type: 'details',
          fields: {
            cover: { rows: 3, pickUrl: '/panel/movies/m1/relations/cover_media_id', pickTarget: 'cover_media_id' } as never,
          },
        },
      ],
    },
  ]

  let fetchMock: ReturnType<typeof vi.fn>

  beforeEach(() => {
    visit.mockClear()
    get.mockClear()
    reload.mockClear()

    fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
      // The media picker's own listing call, made as soon as it opens.
      if (String(url).includes('/api/media')) {
        return new Response(JSON.stringify({ data: [], meta: { total: 0, page: 1, limit: 40, pages: 1 } }), { status: 200 })
      }

      // The field's pickUrl.
      if (String(url) === '/panel/movies/m1/relations/cover_media_id') {
        expect(init?.method).toBe('POST')
        expect(JSON.parse(String(init?.body))).toEqual({ cover_media_id: 'img-1' })

        return new Response(JSON.stringify({ data: {} }), { status: 200 })
      }

      throw new Error(`Unexpected fetch: ${url}`)
    })
    vi.stubGlobal('fetch', fetchMock)
  })

  it('opens the picker, posts the selection, and reloads', async () => {
    document.body.innerHTML = ''
    const wrapper = mount(ResourcePage, {
      attachTo: document.body,
      props: { resource, table, stack, filters: {} },
      attrs: { movies },
      global: { stubs: { teleport: true } },
    })
    await flushPromises()

    const picker = document.querySelector('button[aria-label="Choose Cover"]')
    expect(picker).toBeTruthy()
    picker?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flushPromises()

    // The media dialog is open and has fetched its (empty) listing.
    expect(fetchMock).toHaveBeenCalledWith(expect.stringContaining('/api/media'), expect.anything())

    const dialog = wrapper.findComponent(MediaPickerDialog)
    expect(dialog.props('isOpen')).toBe(true)

    // Simulate the dialog's `select` emit directly — its own click-through UI
    // is exercised by MediaPickerDialog's own tests; this test is about what
    // the page does with the selection.
    dialog.vm.$emit('select', { id: 'img-1', url: '/img/1.jpg', thumbnail_url: '/img/1-thumb.jpg', original_filename: 'x.jpg', alt_text: '', caption: '' })
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      '/panel/movies/m1/relations/cover_media_id',
      expect.objectContaining({ method: 'POST' }),
    )
    expect(reload).toHaveBeenCalledTimes(1)
    expect(dialog.props('isOpen')).toBe(false)

    wrapper.unmount()
  })
})
