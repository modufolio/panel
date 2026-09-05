import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit } }))

const { default: DrawerStack } = await import('../src/Components/Drawer/DrawerStack.vue')

/** Clicking the dimmed page closes every frame: one visit to the base URL. */
describe('DrawerStack backdrop', () => {
  beforeEach(() => {
    visit.mockClear()
    document.body.innerHTML = ''
  })

  it('closes the whole stack and keeps the backdrop reachable', async () => {
    const wrapper = mount(DrawerStack, {
      attachTo: document.body,
      props: {
        baseUrl: '/panel/contacts',
        stack: [
          { type: 'contact', title: 'Denise', data: { id: '1' } },
          { type: 'address', title: 'Office', data: { id: '2' } },
        ],
      },
    })
    await flushPromises()

    const backdrop = document.querySelector('[data-testid="drawer-overlay"]') as HTMLElement
    expect(backdrop).not.toBeNull()
    expect(backdrop.hasAttribute('data-overlay-backdrop')).toBe(true)
    expect(backdrop.getAttribute('aria-hidden')).not.toBe('true')
    expect(backdrop.inert ?? false).toBe(false)

    backdrop.click()
    await flushPromises()

    expect(visit).toHaveBeenCalledTimes(1)
    expect(visit.mock.calls[0][0]).toBe('/panel/contacts')

    wrapper.unmount()
  })
})
