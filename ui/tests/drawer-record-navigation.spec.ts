import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit } }))

const { default: DrawerStack } = await import('../src/Components/Drawer/DrawerStack.vue')

/**
 * Arrow keys walk the records one visit at a time. A press while a visit is
 * still in flight is dropped rather than sent again — and once the visit
 * settles, the next press goes out from whatever frame is on screen then.
 */
describe('Drawer record navigation', () => {
  beforeEach(() => {
    visit.mockClear()
    document.body.innerHTML = ''
  })

  const press = (key: string) => document.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }))

  it('sends one visit per settled press, never two in flight', async () => {
    const wrapper = mount(DrawerStack, {
      attachTo: document.body,
      props: {
        baseUrl: '/panel/contacts',
        stack: [{ type: 'contact', title: 'Denise', data: { id: 'denise' }, nextRecordUrl: '/panel/contacts/annemieke', previousRecordUrl: '/panel/contacts/hans' }],
      },
    })
    await flushPromises()

    press('ArrowDown')
    press('ArrowDown')
    press('ArrowUp')
    expect(visit).toHaveBeenCalledTimes(1)
    expect(visit.mock.calls[0][0]).toBe('/panel/contacts/annemieke')

    // The visit settles and the stack shows the record it loaded.
    visit.mock.calls[0][1].onFinish()
    await wrapper.setProps({
      stack: [{ type: 'contact', title: 'Annemieke', data: { id: 'annemieke' }, nextRecordUrl: '/panel/contacts/rene', previousRecordUrl: '/panel/contacts/denise' }],
    })
    await flushPromises()

    press('ArrowUp')
    expect(visit).toHaveBeenCalledTimes(2)
    expect(visit.mock.calls[1][0]).toBe('/panel/contacts/denise')

    // A failed visit settles too, so the keys are not dead afterwards.
    visit.mock.calls[1][1].onFinish()
    press('ArrowDown')
    expect(visit).toHaveBeenCalledTimes(3)
    expect(visit.mock.calls[2][0]).toBe('/panel/contacts/rene')

    wrapper.unmount()
  })
})
