import type { Ref } from 'vue'
import { isTypeaheadKey, useTypeahead } from './useTypeahead'

export type NavigationOrientation = 'vertical' | 'horizontal' | 'both'

export interface ArrowNavigationOptions {
  /** Which arrow keys move the selection. @default 'vertical' */
  orientation?: NavigationOrientation
  /** Wrap around at the ends. @default true */
  loop?: boolean
}

/**
 * The index an arrow / Home / End press should move to, or -1 when the key is
 * not a navigation key and the caller should let it through.
 *
 * Pure on purpose: a listbox driven by a highlighted index (a combobox) and a
 * menu driven by DOM focus want the same key model but move different things.
 */
export function resolveNavigationIndex(
  key: string,
  currentIndex: number,
  count: number,
  options: ArrowNavigationOptions = {},
): number {
  if (count === 0) return -1

  const { orientation = 'vertical', loop = true } = options
  const vertical = orientation === 'vertical' || orientation === 'both'
  const horizontal = orientation === 'horizontal' || orientation === 'both'

  const step = (delta: number): number => {
    const next = currentIndex + delta
    if (next < 0) return loop ? count - 1 : 0
    if (next > count - 1) return loop ? 0 : count - 1
    return next
  }

  switch (key) {
    case 'ArrowDown': return vertical ? step(1) : -1
    case 'ArrowUp': return vertical ? step(-1) : -1
    case 'ArrowRight': return horizontal ? step(1) : -1
    case 'ArrowLeft': return horizontal ? step(-1) : -1
    case 'Home': return 0
    case 'End': return count - 1
    default: return -1
  }
}

/**
 * Roving focus over the items inside a container — the WAI-ARIA menu keyboard
 * model: arrows move focus between items, Home/End jump to the ends, and typing
 * jumps to an item by name.
 */
export function useArrowNavigation(
  container: Ref<HTMLElement | null>,
  options: ArrowNavigationOptions & { itemSelector?: string } = {},
) {
  const { itemSelector = '[role="menuitem"]', ...navigation } = options
  const { onTypeaheadKey, resetTypeahead } = useTypeahead()

  function items(): HTMLElement[] {
    if (!container.value) return []

    return Array.from(container.value.querySelectorAll<HTMLElement>(itemSelector))
      .filter((item) => !item.hasAttribute('disabled') && item.getAttribute('aria-disabled') !== 'true')
  }

  function focusIndex(index: number): void {
    items()[index]?.focus()
  }

  function focusFirst(): void {
    focusIndex(0)
  }

  /** Feed the container's keydown in; returns whether the press was consumed. */
  function onKeydown(event: KeyboardEvent): boolean {
    const list = items()
    if (list.length === 0) return false

    const current = list.indexOf(document.activeElement as HTMLElement)

    const next = resolveNavigationIndex(event.key, current, list.length, navigation)
    if (next !== -1) {
      event.preventDefault()
      focusIndex(next)
      return true
    }

    if (isTypeaheadKey(event)) {
      const match = onTypeaheadKey(event.key, list.map((item) => item.textContent ?? ''), current)
      if (match !== -1) {
        event.preventDefault()
        focusIndex(match)
        return true
      }
    }

    return false
  }

  return { items, focusFirst, focusIndex, onKeydown, resetTypeahead }
}
