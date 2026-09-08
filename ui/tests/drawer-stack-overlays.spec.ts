import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

vi.mock('@inertiajs/vue3', () => ({ router: { prefetch: vi.fn(), flushAll: vi.fn(), visit: vi.fn() } }))

const { default: DrawerStack } = await import('../src/Components/Drawer/DrawerStack.vue')

const stack = [{ type: 'movie', title: 'Arrival', data: { id: 'm1', title: 'Arrival' } }]

/**
 * A panel the page draws over the stack — the add form — stands on it: the
 * drawers shift left for it the way they do for another frame. Without this
 * the form appeared beside a stack that had not moved, and the drawer under
 * it went on answering the record-navigation keys.
 */
describe('DrawerStack with an overlay panel', () => {
  it('shifts the drawers left for it', async () => {
    document.body.innerHTML = ''
    const wrapper = mount(DrawerStack, { attachTo: document.body, props: { stack } })
    await flushPromises()

    const drawer = document.querySelector('[data-testid="drawer-level-0"]') as HTMLElement
    expect(drawer.style.transform).toBe('')

    await wrapper.setProps({ overlays: 1 })
    await flushPromises()

    expect(drawer.style.transform).toBe('translateX(-150px)')

    wrapper.unmount()
  })
})
