import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({
  // A real anchor, so the test sees the href a browser would follow.
  Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { visit },
}))

const { default: RecordNavigation } = await import('../src/Components/Layout/RecordNavigation.vue')

const mounted: Array<{ unmount: () => void }> = []

const mountNav = (props: Record<string, unknown>) => {
  const wrapper = mount(RecordNavigation, { props, attachTo: document.body })
  mounted.push(wrapper)
  return wrapper
}

const press = (key: string, target?: Element, init: KeyboardEventInit = {}) =>
  (target ?? window).dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, ...init }))

/**
 * The shared prev/next pair: what it renders on each side, and the arrow keys
 * every page that mounts it inherits.
 */
describe('RecordNavigation', () => {
  beforeEach(() => visit.mockClear())
  afterEach(() => {
    while (mounted.length > 0) mounted.pop()?.unmount()
    document.body.innerHTML = ''
  })

  it('links each side that has a neighbour', () => {
    const wrapper = mountNav({ previous: '/movies/heat/edit', next: '/movies/jaws/edit' })

    expect(wrapper.get('[data-testid="record-nav-previous"]').attributes('href')).toBe('/movies/heat/edit')
    expect(wrapper.get('[data-testid="record-nav-next"]').attributes('href')).toBe('/movies/jaws/edit')
  })

  it('keeps a missing side in place as a dead button, so the pair does not shift between records', () => {
    const wrapper = mountNav({ previous: null, next: '/movies/jaws/edit' })
    const previous = wrapper.get('[data-testid="record-nav-previous"]')

    expect(previous.element.tagName).toBe('SPAN')
    expect(previous.attributes('href')).toBeUndefined()
    expect(previous.attributes('aria-disabled')).toBe('true')
  })

  it('names the record in the tooltip and the label', () => {
    const wrapper = mountNav({ previous: '/posts/a/edit', next: null, label: 'post' })

    expect(wrapper.get('[data-testid="record-nav-previous"]').attributes('title')).toBe('Previous post (←)')
    expect(wrapper.get('[data-testid="record-nav-next"]').attributes('aria-label')).toBe('Next post (none)')
  })

  it('steps with the left and right arrow keys', () => {
    mountNav({ previous: '/movies/heat/edit', next: '/movies/jaws/edit' })

    press('ArrowRight')
    expect(visit).toHaveBeenCalledWith('/movies/jaws/edit')

    press('ArrowLeft')
    expect(visit).toHaveBeenCalledWith('/movies/heat/edit')
  })

  it('leaves the arrows to the field that has focus, and chords to the browser', () => {
    mountNav({ previous: null, next: '/movies/jaws/edit' })

    const input = document.createElement('input')
    document.body.appendChild(input)
    press('ArrowRight', input)
    press('ArrowRight', undefined, { metaKey: true })

    expect(visit).not.toHaveBeenCalled()
    input.remove()
  })

  it('does not step towards a neighbour that is not there', () => {
    mountNav({ previous: null, next: null })

    press('ArrowLeft')
    press('ArrowRight')
    expect(visit).not.toHaveBeenCalled()
  })

  it('leaves the keys alone when the page asked to keep them', () => {
    mountNav({ previous: '/movies/heat/edit', next: '/movies/jaws/edit', keyboard: false })

    press('ArrowRight')
    expect(visit).not.toHaveBeenCalled()
  })

  it('stops listening once unmounted', () => {
    const wrapper = mountNav({ previous: null, next: '/movies/jaws/edit' })
    wrapper.unmount()

    press('ArrowRight')
    expect(visit).not.toHaveBeenCalled()
  })
})
