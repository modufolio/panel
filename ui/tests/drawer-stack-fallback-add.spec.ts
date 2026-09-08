import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { StackItem } from '../src/Components/Drawer/useDrawerStack'

vi.mock('@inertiajs/vue3', () => ({ router: { prefetch: vi.fn(), flushAll: vi.fn(), visit: vi.fn() } }))

const { default: DrawerStack } = await import('../src/Components/Drawer/DrawerStack.vue')

function stackWith(section: Record<string, unknown>): StackItem[] {
  return [
    {
      type: 'movie',
      title: 'Arrival',
      data: { id: 'm1', title: 'Arrival', cast: [{ id: 'c1', name: 'Amy Adams' }] },
      tabs: [
        {
          key: 'details',
          label: 'Details',
          type: 'details' as const,
          sections: [
            { key: 'cast', label: 'Cast', type: 'relation' as const, source: 'cast', primary: 'name', ...section },
          ],
        },
      ],
    },
  ]
}

/**
 * A frame of another resource has no slot on the page, so the stack renders it
 * itself. The add action is the record's own — the server stamps the endpoint
 * onto the list — so the stack offers it and hands the frame along with the
 * list, rather than dropping the emit on the floor as it used to.
 */
describe('DrawerStack rendering a frame itself', () => {
  it('offers the list add action and emits it with the frame', async () => {
    document.body.innerHTML = ''
    const wrapper = mount(DrawerStack, {
      attachTo: document.body,
      props: {
        baseUrl: '/panel/actors',
        stack: stackWith({ addable: true, addLabel: '+ Add', addUrl: '/panel/movies/m1/relations/cast' }),
      },
    })
    await flushPromises()

    const add = Array.from(document.querySelectorAll('button')).find((el) => el.textContent?.includes('+ Add'))
    expect(add).toBeTruthy()

    add?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flushPromises()

    const emitted = wrapper.emitted('add')
    expect(emitted).toHaveLength(1)
    expect((emitted?.[0][0] as { addUrl: string }).addUrl).toBe('/panel/movies/m1/relations/cast')
    expect((emitted?.[0][1] as { type: string }).type).toBe('movie')

    wrapper.unmount()
  })

  /** No endpoint means the server would turn the row away — so no button. */
  it('draws no add action when the server stamped no endpoint', async () => {
    document.body.innerHTML = ''
    const wrapper = mount(DrawerStack, {
      attachTo: document.body,
      props: { baseUrl: '/panel/actors', stack: stackWith({ addable: true, addLabel: '+ Add' }) },
    })
    await flushPromises()

    expect(document.body.textContent).toContain('Amy Adams')
    expect(document.body.textContent).not.toContain('+ Add')

    wrapper.unmount()
  })
})
