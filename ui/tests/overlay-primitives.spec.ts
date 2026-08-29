import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { mount, config, enableAutoUnmount } from '@vue/test-utils'
import {
  Dialog,
  Drawer,
  DrawerStack,
  hideOthers,
  isHiddenBehindOverlay,
  resolveNavigationIndex,
  useBodyScrollLock,
  useDismissableLayer,
  useId,
  useTypeahead,
} from '../src/index'

/** Run a composable in its own scope, so disposal can be tested. */
function inScope<T>(setup: () => T): { value: T; dispose: () => void } {
  const scope = effectScope()
  const value = scope.run(setup) as T
  return { value, dispose: () => scope.stop() }
}

// Overlays left mounted keep a live focus trap, which would reach into the
// next test's DOM.
enableAutoUnmount(afterEach)

describe('useBodyScrollLock', () => {
  afterEach(() => {
    document.body.style.overflow = ''
    document.body.style.paddingRight = ''
  })

  it('keeps the page locked until every holder releases', async () => {
    const drawer = inScope(() => useBodyScrollLock())
    const dialog = inScope(() => useBodyScrollLock())

    drawer.value.value = true
    dialog.value.value = true
    await nextTick()
    expect(document.body.style.overflow).toBe('hidden')

    // The dialog closing must not unlock the page under the drawer that is
    // still covering it — the bug this replaces.
    dialog.value.value = false
    await nextTick()
    expect(document.body.style.overflow).toBe('hidden')

    drawer.value.value = false
    await nextTick()
    expect(document.body.style.overflow).toBe('')

    drawer.dispose()
    dialog.dispose()
  })

  it('releases the lock when the holder is disposed without closing', async () => {
    const holder = inScope(() => useBodyScrollLock(true))
    await nextTick()
    expect(document.body.style.overflow).toBe('hidden')

    holder.dispose()
    expect(document.body.style.overflow).toBe('')
  })

  it('restores the body styles it found rather than clearing them', async () => {
    document.body.style.overflow = 'scroll'

    const holder = inScope(() => useBodyScrollLock(true))
    await nextTick()
    expect(document.body.style.overflow).toBe('hidden')

    holder.dispose()
    expect(document.body.style.overflow).toBe('scroll')
  })
})

describe('useDismissableLayer', () => {
  let elements: HTMLElement[] = []

  function openLayer(options: { onDismiss: (reason: string) => void; outside?: boolean }) {
    const element = document.createElement('div')
    element.appendChild(document.createElement('button'))
    document.body.appendChild(element)
    elements.push(element)

    const open = ref(true)
    const scope = effectScope()
    scope.run(() => useDismissableLayer(open, {
      elements: () => [element],
      onDismiss: (reason) => options.onDismiss(reason),
      dismissOnOutsidePointer: options.outside ?? true,
    }))

    return { element, open, dispose: () => scope.stop() }
  }

  afterEach(() => {
    for (const element of elements) element.remove()
    elements = []
  })

  it('sends Escape to the topmost layer only', () => {
    const bottom = vi.fn()
    const top = vi.fn()

    const lower = openLayer({ onDismiss: bottom })
    const upper = openLayer({ onDismiss: top })

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(top).toHaveBeenCalledWith('escape')
    expect(bottom).not.toHaveBeenCalled()

    upper.dispose()
    lower.dispose()
  })

  it('hands Escape back to the layer beneath once the top one closes', async () => {
    const bottom = vi.fn()
    const top = vi.fn()

    const lower = openLayer({ onDismiss: bottom })
    const upper = openLayer({ onDismiss: top })

    upper.open.value = false
    await nextTick()

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(bottom).toHaveBeenCalledWith('escape')
    expect(top).not.toHaveBeenCalled()

    upper.dispose()
    lower.dispose()
  })

  it('ignores a press inside the layer and dismisses on one outside', () => {
    const dismiss = vi.fn()
    const layer = openLayer({ onDismiss: dismiss })

    layer.element.querySelector('button')!.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(dismiss).not.toHaveBeenCalled()

    const outside = document.createElement('button')
    document.body.appendChild(outside)
    outside.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(dismiss).toHaveBeenCalledWith('pointer-outside')

    outside.remove()
    layer.dispose()
  })

  it('leaves an outside press alone when the layer opted out', () => {
    const dismiss = vi.fn()
    const layer = openLayer({ onDismiss: dismiss, outside: false })

    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(dismiss).not.toHaveBeenCalled()

    layer.dispose()
  })

  it('hides the rest of the page from assistive technology while modal', async () => {
    const page = document.createElement('main')
    document.body.appendChild(page)

    const panel = document.createElement('div')
    document.body.appendChild(panel)

    const open = ref(true)
    const scope = effectScope()
    scope.run(() => useDismissableLayer(open, {
      elements: () => [panel],
      onDismiss: () => {},
      modalElement: () => panel,
    }))

    await nextTick()
    expect(isHiddenBehindOverlay(page)).toBe(true)
    expect(isHiddenBehindOverlay(panel)).toBe(false)

    open.value = false
    await nextTick()
    expect(isHiddenBehindOverlay(page)).toBe(false)

    scope.stop()
    page.remove()
    panel.remove()
  })
})

describe('Dialog stacking', () => {
  beforeEach(() => {
    config.global = { stubs: { Teleport: true } } as any
  })

  afterEach(() => {
    config.global = {} as any
    document.body.style.overflow = ''
  })

  it('closes only the dialog on top when Escape is pressed', async () => {
    const lower = mount(Dialog, { props: { isOpen: true, title: 'Lower' } })
    const upper = mount(Dialog, { props: { isOpen: true, title: 'Upper' } })
    await nextTick()

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(upper.emitted('close')).toBeTruthy()
    expect(lower.emitted('close')).toBeFalsy()
  })
})

describe('focus trapping across stacked overlays', () => {
  beforeEach(() => {
    config.global = { stubs: { Teleport: true } } as any
  })

  afterEach(() => {
    config.global = {} as any
    document.body.style.overflow = ''
  })

  /** Mount an open drawer and wait for its trap to activate (a 100ms timer). */
  async function openDrawer() {
    const drawer = mount(Drawer, { props: { isOpen: true, title: 'Arrival' }, attachTo: document.body })
    await nextTick()
    await new Promise((resolve) => setTimeout(resolve, 150))
    return drawer
  }

  function focus(element: HTMLElement) {
    element.focus()
    element.dispatchEvent(new FocusEvent('focusin', { bubbles: true }))
  }

  it('pulls focus back when it escapes to the page behind', async () => {
    const page = document.createElement('main')
    page.innerHTML = '<button id="behind">Behind</button>'
    document.body.appendChild(page)

    await openDrawer()
    expect(isHiddenBehindOverlay(page)).toBe(true)

    focus(document.getElementById('behind') as HTMLElement)
    await nextTick()

    // Pulled back to the drawer's first focusable, its close button.
    expect(document.activeElement).not.toBe(document.getElementById('behind'))
    expect(document.activeElement?.getAttribute('aria-label')).toBe('Close drawer')

    page.remove()
  })

  it('takes the page out with inert, so the browser moves focus instead of erroring', async () => {
    const page = document.createElement('main')
    page.innerHTML = '<a id="opener" href="#x">Open</a>'
    document.body.appendChild(page)

    await openDrawer()

    // `aria-hidden` on an ancestor of the focused element is a spec violation
    // the browser refuses ("Blocked aria-hidden ... descendant retained
    // focus"); `inert` moves focus out on its own.
    expect(page.hasAttribute('inert')).toBe(true)
    expect(page.getAttribute('aria-hidden')).toBe(null)

    page.remove()
  })

  it('returns focus to whatever opened the drawer', async () => {
    const page = document.createElement('main')
    page.innerHTML = '<a id="opener" href="#x">Open</a>'
    document.body.appendChild(page)

    // Let any focus-restore timer from a previously closed overlay land first.
    await new Promise((resolve) => setTimeout(resolve, 20))

    const opener = document.getElementById('opener') as HTMLElement
    opener.focus()
    expect(document.activeElement).toBe(opener)

    const drawer = mount(Drawer, { props: { isOpen: true, title: 'Arrival' }, attachTo: document.body })
    await nextTick()

    // What a real browser does the moment `inert` lands on the opener's
    // ancestor. The trap must already have recorded the opener by now, which is
    // why it is activated synchronously rather than on a timer.
    opener.blur()
    await new Promise((resolve) => setTimeout(resolve, 150))

    await drawer.setProps({ isOpen: false })
    await new Promise((resolve) => setTimeout(resolve, 150))

    expect(document.activeElement).toBe(opener)

    page.remove()
  })

  it('leaves focus inside a dialog opened above the drawer', async () => {
    await openDrawer()

    // Teleported beside the drawer, not into it — the drawer's trap must yield.
    mount(Dialog, {
      props: { isOpen: true, title: 'Add Cast' },
      slots: { default: '<input id="cast-search" />' },
      attachTo: document.body,
    })
    await nextTick()
    await new Promise((resolve) => setTimeout(resolve, 150))

    const input = document.getElementById('cast-search') as HTMLInputElement
    focus(input)
    await nextTick()

    expect(document.activeElement).toBe(input)
  })

  it('leaves focus inside an overlay the host app rendered itself', async () => {
    await openDrawer()

    // A raw teleported panel: no layer, no aria-hidden. Recapturing here closed
    // a combobox the moment it opened, because the blur reverted its dropdown.
    const panel = document.createElement('div')
    panel.innerHTML = '<input id="adhoc-search" />'
    document.body.appendChild(panel)

    const input = document.getElementById('adhoc-search') as HTMLInputElement
    focus(input)
    await nextTick()

    expect(document.activeElement).toBe(input)

    panel.remove()
  })
})

describe('DrawerStack', () => {
  beforeEach(() => {
    config.global = { stubs: { Teleport: true } } as any
  })

  afterEach(() => {
    config.global = {} as any
    document.body.style.overflow = ''
  })

  const arrival = { key: 'a', type: 'movie', title: 'Arrival', data: { id: 1 } }
  const amy = { key: 'b', type: 'person', title: 'Amy Adams', data: { id: 2 } }

  it('closes the drawer on top, not the one beneath', async () => {
    const wrapper = mount(DrawerStack, { props: { stack: [arrival, amy] } as any })
    await nextTick()

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    // closeFrom(1) — the top frame. Closing the bottom one emits 'close:all'.
    expect(wrapper.emitted('close')?.[0]).toEqual([1])
    expect(wrapper.emitted('close:all')).toBeFalsy()
  })

  it('keeps the order when the stack grows a level at a time', async () => {
    const wrapper = mount(DrawerStack, { props: { stack: [arrival] } as any })
    await nextTick()

    // How the real stack deepens: a navigation replaces the prop.
    await wrapper.setProps({ stack: [arrival, amy] } as any)
    await nextTick()

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(wrapper.emitted('close')?.[0]).toEqual([1])
    expect(wrapper.emitted('close:all')).toBeFalsy()
  })
})

describe('hideOthers', () => {
  it('restores the page only after the last overlay releases', () => {
    const page = document.createElement('main')
    const first = document.createElement('div')
    const second = document.createElement('div')
    document.body.append(page, first, second)

    const undoFirst = hideOthers(first)
    const undoSecond = hideOthers(second, [first])

    expect(isHiddenBehindOverlay(page)).toBe(true)
    expect(isHiddenBehindOverlay(first)).toBe(false)

    undoSecond()
    expect(isHiddenBehindOverlay(page)).toBe(true)

    undoFirst()
    expect(isHiddenBehindOverlay(page)).toBe(false)

    page.remove()
    first.remove()
    second.remove()
  })

  it('leaves a live region announcing', () => {
    const toasts = document.createElement('div')
    toasts.setAttribute('aria-live', 'polite')
    const panel = document.createElement('div')
    document.body.append(toasts, panel)

    const undo = hideOthers(panel)
    expect(isHiddenBehindOverlay(toasts)).toBe(false)

    undo()
    toasts.remove()
    panel.remove()
  })
})

describe('resolveNavigationIndex', () => {
  it('moves through the list and wraps when asked to', () => {
    expect(resolveNavigationIndex('ArrowDown', 0, 3)).toBe(1)
    expect(resolveNavigationIndex('ArrowDown', 2, 3)).toBe(0)
    expect(resolveNavigationIndex('ArrowUp', 0, 3)).toBe(2)
    expect(resolveNavigationIndex('Home', 2, 3)).toBe(0)
    expect(resolveNavigationIndex('End', 0, 3)).toBe(2)
  })

  it('stops at the ends when looping is off', () => {
    expect(resolveNavigationIndex('ArrowDown', 2, 3, { loop: false })).toBe(2)
    expect(resolveNavigationIndex('ArrowUp', 0, 3, { loop: false })).toBe(0)
  })

  it('ignores the axis it does not own', () => {
    expect(resolveNavigationIndex('ArrowRight', 0, 3)).toBe(-1)
    expect(resolveNavigationIndex('ArrowRight', 0, 3, { orientation: 'horizontal' })).toBe(1)
    expect(resolveNavigationIndex('a', 0, 3)).toBe(-1)
  })

  it('reports nothing to move to for an empty list', () => {
    expect(resolveNavigationIndex('ArrowDown', -1, 0)).toBe(-1)
  })
})

describe('useTypeahead', () => {
  const values = ['Apple', 'Apricot', 'Banana']

  it('jumps to the first item matching what was typed', () => {
    const { onTypeaheadKey } = useTypeahead()

    expect(onTypeaheadKey('b', values, 0)).toBe(2)
  })

  it('narrows as more characters arrive', () => {
    const { onTypeaheadKey } = useTypeahead()

    expect(onTypeaheadKey('a', values, -1)).toBe(0)
    expect(onTypeaheadKey('p', values, 0)).toBe(0)
    expect(onTypeaheadKey('r', values, 0)).toBe(1)
  })

  it('cycles through the matches when one character repeats', () => {
    const { onTypeaheadKey } = useTypeahead()

    expect(onTypeaheadKey('a', values, -1)).toBe(0)
    expect(onTypeaheadKey('a', values, 0)).toBe(1)
  })

  it('reports no match rather than moving', () => {
    const { onTypeaheadKey } = useTypeahead()

    expect(onTypeaheadKey('z', values, 0)).toBe(-1)
  })
})

describe('useId', () => {
  it('returns a caller-supplied id untouched', () => {
    expect(useId('given-id')).toBe('given-id')
  })

  it('prefixes generated ids', () => {
    expect(useId(undefined, 'field')).toMatch(/^field-/)
  })
})
