import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const visit = vi.fn()
const prefetch = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit, prefetch, flushAll: vi.fn() } }))

const { default: DrawerStack } = await import('../src/Components/Drawer/DrawerStack.vue')
const { DRAWER_HEADER } = await import('../src/Components/Drawer/useIsDrawer')
const { PREFETCH_CACHE_FOR } = await import('../src/Components/Drawer/visitDrawer')

/**
 * An open drawer fetches its neighbours ahead, with exactly the request an
 * arrow key would make — same header, same partial-reload shape — so the
 * visit is served from the cache instead of the server.
 */
describe('Drawer neighbour prefetch', () => {
  beforeEach(() => {
    prefetch.mockClear()
    document.body.innerHTML = ''
  })

  it('prefetches the next and previous record as drawer visits', async () => {
    const wrapper = mount(DrawerStack, {
      attachTo: document.body,
      props: {
        baseUrl: '/panel/contacts',
        stack: [{ type: 'contact', title: 'Denise', data: { id: 'denise' }, nextRecordUrl: '/panel/contacts/annemieke', previousRecordUrl: '/panel/contacts/hans' }],
      },
    })
    await flushPromises()

    const urls = prefetch.mock.calls.map((call) => call[0]).sort()
    expect(urls).toEqual(['/panel/contacts/annemieke', '/panel/contacts/hans'])

    const [, options, cache] = prefetch.mock.calls[0]
    expect(options.method).toBe('get')
    expect(options.headers).toEqual({ [DRAWER_HEADER]: '1' })
    expect(cache).toEqual({ cacheFor: PREFETCH_CACHE_FOR })

    wrapper.unmount()
  })

  it('prefetches only what the frame names', async () => {
    const wrapper = mount(DrawerStack, {
      attachTo: document.body,
      props: {
        baseUrl: '/panel/contacts',
        stack: [{ type: 'contact', title: 'Only one', data: { id: 'solo' } }],
      },
    })
    await flushPromises()

    expect(prefetch).not.toHaveBeenCalled()

    wrapper.unmount()
  })
})
