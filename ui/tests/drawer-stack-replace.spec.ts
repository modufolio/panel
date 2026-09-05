import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit } }))

const { default: DrawerStack } = await import('../src/Components/Drawer/DrawerStack.vue')

/**
 * Arrow-key record navigation replaces the stack prop with a frame for the
 * next record. The drawer must show that record: a stale frame here is what
 * "the next contact only appears after clicking the backdrop" looks like.
 */
describe('DrawerStack when the stack prop is replaced', () => {
  it('renders the new frame and drops the old one', async () => {
    document.body.innerHTML = ''
    const wrapper = mount(DrawerStack, {
      attachTo: document.body,
      props: {
        baseUrl: '/panel/contacts',
        stack: [{ type: 'contact', title: 'Anna', data: { id: 'a', first_name: 'Anna' }, nextRecordUrl: '/panel/contacts/b' }],
      },
    })
    await flushPromises()
    expect(document.body.textContent).toContain('Anna')

    // What Inertia does after the visit: a new array, a new record.
    await wrapper.setProps({
      stack: [{ type: 'contact', title: 'Bert', data: { id: 'b', first_name: 'Bert' }, previousRecordUrl: '/panel/contacts/a' }],
    })
    await flushPromises()

    const titles = Array.from(document.querySelectorAll('[data-testid="drawer-title"]')).map((el) => el.textContent?.trim())
    expect(titles).toEqual(['Bert'])
    expect(document.body.textContent).toContain('Bert')
    expect(document.body.textContent).not.toContain('Anna')

    // And the key handler of the live drawer answers ArrowUp with the new frame's link.
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }))
    expect(visit).toHaveBeenCalledTimes(1)
    expect(visit.mock.calls[0][0]).toBe('/panel/contacts/a')

    wrapper.unmount()
  })
})
