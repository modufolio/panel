import { ref, type Ref } from 'vue'
import { useEventListener, useTimeoutFn } from '@vueuse/core'
import { hasModalLayer, isTopmostModalLayer } from '../Primitives/useDismissableLayer'
import { isHiddenBehindOverlay } from '../Primitives/hideOthers'

const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'textarea:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'details > summary',
  'iframe',
  'audio[controls]',
  'video[controls]',
  '[contenteditable]:not([contenteditable="false"])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

export function useFocusTrap(containerRef: Ref<HTMLElement | null>) {
  const previousFocus = ref<HTMLElement | null>(null)
  let active = false

  const getFocusableElements = (): HTMLElement[] => {
    if (!containerRef.value) return []

    return Array.from(containerRef.value.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR))
      .filter((element) => !element.hasAttribute('hidden') && !element.closest('[inert]'))
  }

  /**
   * A trap holds focus only while its overlay is the one on top. A dialog
   * opened from inside a drawer is teleported beside it, not into it, so a
   * lower trap that kept enforcing would pull focus out of the dialog the user
   * is working in. Traps used on their own, outside any layer, always hold.
   */
  const owns = (): boolean => {
    if (!active) return false
    return !hasModalLayer() || isTopmostModalLayer(containerRef.value)
  }

  const trapFocus = (event: KeyboardEvent) => {
    if (!owns() || event.key !== 'Tab') return

    const focusables = getFocusableElements()
    if (focusables.length === 0) return

    const firstElement = focusables[0]
    const lastElement = focusables[focusables.length - 1]

    // If shift+tab on first element, move to last
    if (event.shiftKey && document.activeElement === firstElement) {
      lastElement.focus()
      event.preventDefault()
    }
    // If tab on last element, move to first
    else if (!event.shiftKey && document.activeElement === lastElement) {
      firstElement.focus()
      event.preventDefault()
    }
  }

  /**
   * Tab wrapping alone is not a trap: focus also escapes through a click on the
   * page behind, a programmatic `focus()`, or the browser's own address bar
   * round-trip. Watching `focusin` catches every one of those and pulls focus
   * back to where the user can see it.
   */
  const recaptureFocus = (event: FocusEvent) => {
    if (!owns()) return

    const container = containerRef.value
    const target = event.target as HTMLElement | null
    if (!container || !target || container.contains(target)) return

    // Only fight focus that escaped to the page *behind* this overlay — the
    // part the layer stack took out of the page when the overlay opened.
    // Anything else that took focus is above us: an overlay the host app
    // rendered itself, a browser widget. Dragging focus out of that is worse
    // than letting it go, and it is what made a combobox inside such a panel
    // close the moment it opened.
    if (hasModalLayer() && !isHiddenBehindOverlay(target)) return

    const focusables = getFocusableElements()
    if (focusables.length > 0) {
      focusables[0].focus()
      return
    }

    // Nothing focusable inside: hold focus on the container itself, so it stays
    // in the overlay rather than falling back to the page behind it.
    if (!container.hasAttribute('tabindex')) container.setAttribute('tabindex', '-1')
    container.focus()
  }

  // useEventListener auto-removes the listeners on scope dispose; `active`
  // gates them so focus is only trapped between activate()/deactivate().
  useEventListener(document, 'keydown', trapFocus)
  useEventListener(document, 'focusin', recaptureFocus)

  const focusFirst = useTimeoutFn(() => {
    const focusables = getFocusableElements()
    if (focusables.length > 0) focusables[0].focus()
  }, 10, { immediate: false })

  const restore = useTimeoutFn(() => {
    if (previousFocus.value && typeof previousFocus.value.focus === 'function') {
      previousFocus.value.focus()
    }
  }, 10, { immediate: false })

  const activate = () => {
    if (active) return
    active = true
    previousFocus.value = document.activeElement as HTMLElement
    focusFirst.start()
  }

  const deactivate = () => {
    focusFirst.stop()
    if (!active) return
    active = false
    restore.start()
  }

  return {
    activate,
    deactivate
  }
}
