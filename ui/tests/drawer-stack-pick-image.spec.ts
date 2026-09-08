import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { StackItem } from '../src/Components/Drawer/useDrawerStack'

vi.mock('@inertiajs/vue3', () => ({ router: { prefetch: vi.fn(), flushAll: vi.fn(), visit: vi.fn() } }))

const { default: DrawerStack } = await import('../src/Components/Drawer/DrawerStack.vue')

function stackWith(cover: Record<string, unknown>): StackItem[] {
  return [
    {
      type: 'movie',
      title: 'Arrival',
      data: { id: 'm1', title: 'Arrival', cover: null },
      tabs: [
        {
          key: 'details',
          label: 'Details',
          type: 'details' as const,
          fields: { cover: cover as never },
        },
      ],
    },
  ]
}

/**
 * A `pickable` field with no value yet (a cover the record has not been
 * given) renders as a clickable affordance rather than a blank square, and
 * clicking it bubbles `pick-image` up through the frame with the field the
 * server stamped `pickUrl`/`pickTarget` onto, and the frame it belongs to —
 * the same shape `add` already bubbles in, for the same reason: a stacked
 * frame of another resource must post to *its own* endpoint.
 */
describe('DrawerStack rendering a pickable field itself', () => {
  it('offers the picker and emits pick-image with the field and the frame', async () => {
    document.body.innerHTML = ''
    const wrapper = mount(DrawerStack, {
      attachTo: document.body,
      props: {
        baseUrl: '/panel/movies',
        stack: stackWith({ rows: 3, pickUrl: '/panel/movies/m1/relations/cover_media_id', pickTarget: 'cover_media_id' }),
      },
    })
    await flushPromises()

    const picker = document.querySelector('button[aria-label="Choose Cover"]')
    expect(picker).toBeTruthy()

    picker?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flushPromises()

    const emitted = wrapper.emitted('pick-image')
    expect(emitted).toHaveLength(1)
    expect((emitted?.[0][0] as { pickUrl: string }).pickUrl).toBe('/panel/movies/m1/relations/cover_media_id')
    expect((emitted?.[0][0] as { pickTarget: string }).pickTarget).toBe('cover_media_id')
    expect((emitted?.[0][1] as { type: string }).type).toBe('movie')

    wrapper.unmount()
  })

  /** No endpoint means the server would turn the write away — so no picker. */
  it('draws a plain placeholder when the server stamped no endpoint', async () => {
    document.body.innerHTML = ''
    const wrapper = mount(DrawerStack, {
      attachTo: document.body,
      props: { baseUrl: '/panel/movies', stack: stackWith({ rows: 3 }) },
    })
    await flushPromises()

    expect(document.querySelector('button[aria-label="Choose Cover"]')).toBeFalsy()

    wrapper.unmount()
  })
})
