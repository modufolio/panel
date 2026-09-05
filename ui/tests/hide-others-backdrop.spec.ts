import { describe, it, expect, afterEach } from 'vitest'
import { hideOthers } from '../src/Primitives/hideOthers'

/**
 * A drawer marks itself modal and the rest of the page is hidden. The stack's
 * shared backdrop is a sibling of the drawer under body — hidden with the
 * rest, it would still dim the page but ignore the click that closes the
 * stack, since an inert element receives no pointer events.
 */
describe('hideOthers and the overlay backdrop', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('leaves a backdrop reachable while hiding the page beside it', () => {
    document.body.innerHTML = `
      <main id="page">content</main>
      <div id="backdrop" data-overlay-backdrop></div>
      <div id="drawer" role="dialog">drawer</div>
    `
    const drawer = document.getElementById('drawer')!
    const page = document.getElementById('page')!
    const backdrop = document.getElementById('backdrop')!

    const undo = hideOthers(drawer)

    const hidden = (el: HTMLElement) => el.inert === true || el.getAttribute('aria-hidden') === 'true'
    expect(hidden(page)).toBe(true)
    expect(hidden(backdrop)).toBe(false)

    undo()
    expect(hidden(page)).toBe(false)
  })
})
