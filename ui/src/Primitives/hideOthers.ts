/**
 * How many open overlays are hiding each element, and what the element looked
 * like before the first of them. Stacked overlays hide overlapping sets of
 * siblings, so the treatment may only be undone by the last one out.
 */
const hideCounts = new WeakMap<Element, number>()
const originalAriaHidden = new WeakMap<Element, string | null>()
const originalInert = new WeakMap<Element, boolean>()

/**
 * `inert` both hides a subtree from assistive technology and takes it out of
 * the focus order — and, unlike `aria-hidden`, applying it to an ancestor of
 * the focused element moves focus out instead of producing the "Blocked
 * aria-hidden on an element because its descendant retained focus" violation.
 */
const supportsInert = typeof HTMLElement !== 'undefined' && 'inert' in HTMLElement.prototype

/** Elements that must stay announced and reachable — a toast keeps speaking. */
function isExempt(element: Element): boolean {
  if (element.tagName === 'SCRIPT' || element.tagName === 'STYLE' || element.tagName === 'LINK') {
    return true
  }

  // A live region, or a container holding one: a toast stack teleported beside
  // the overlay is a sibling of it, and silencing it would swallow the very
  // messages the overlay's own actions produce.
  return element.hasAttribute('aria-live') || !!element.querySelector('[aria-live]')
}

function hide(element: Element): void {
  if (supportsInert) {
    originalInert.set(element, (element as HTMLElement).inert)
    ;(element as HTMLElement).inert = true
    return
  }

  originalAriaHidden.set(element, element.getAttribute('aria-hidden'))

  // Without `inert` the browser will not move focus for us, and hiding an
  // ancestor of the focused element is the violation this avoids.
  const active = document.activeElement
  if (active instanceof HTMLElement && element.contains(active)) active.blur()

  element.setAttribute('aria-hidden', 'true')
}

function restore(element: Element): void {
  if (supportsInert) {
    ;(element as HTMLElement).inert = originalInert.get(element) ?? false
    return
  }

  const original = originalAriaHidden.get(element)
  if (original === null || original === undefined) element.removeAttribute('aria-hidden')
  else element.setAttribute('aria-hidden', original)
}

/**
 * Take everything outside `target` out of the accessibility tree, walking up to
 * the body and treating each level's siblings. Returns the undo.
 *
 * @param exempt Elements to leave alone — the other open overlays. They are
 *   siblings of `target` under the teleport root, and hiding a dropdown that
 *   belongs to the dialog on top of it would silence the very control the user
 *   just opened.
 */
export function hideOthers(target: Element, exempt: Element[] = []): () => void {
  const hidden: Element[] = []
  let node: Element | null = target

  while (node && node !== document.body && node.parentElement) {
    for (const sibling of Array.from(node.parentElement.children)) {
      if (sibling === node || isExempt(sibling)) continue
      if (exempt.some((element) => element === sibling || sibling.contains(element))) continue

      const count = hideCounts.get(sibling) ?? 0
      if (count === 0) hide(sibling)

      hideCounts.set(sibling, count + 1)
      hidden.push(sibling)
    }

    node = node.parentElement
  }

  return () => {
    for (const element of hidden) {
      const count = (hideCounts.get(element) ?? 1) - 1
      hideCounts.set(element, count)
      if (count === 0) restore(element)
    }
  }
}

/** Whether `element` sits in a subtree an overlay has taken out of the page. */
export function isHiddenBehindOverlay(element: HTMLElement): boolean {
  return !!element.closest('[inert], [aria-hidden="true"]')
}
