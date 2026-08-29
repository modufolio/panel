import { onScopeDispose, ref, watch, type Ref } from 'vue'

/**
 * Every overlay currently asking for the lock. Reference counting is the whole
 * point: a dialog opened from inside a drawer used to restore `overflow` when
 * it closed, unlocking the page while the drawer was still covering it.
 */
const holders = new Set<object>()

/** The body's own inline styles, captured when the first holder arrives. */
let restore: { overflow: string; paddingRight: string } | null = null

function sync(): void {
  if (typeof document === 'undefined') return

  const body = document.body

  if (holders.size > 0) {
    if (!restore) {
      restore = { overflow: body.style.overflow, paddingRight: body.style.paddingRight }
      applyScrollbarCompensation(body)
    }

    // Re-asserted on every change rather than only when the first holder
    // arrives: anything else that touched `body.style.overflow` in between
    // would otherwise leave the page scrolling under an open overlay.
    body.style.overflow = 'hidden'
    return
  }

  if (!restore) return

  body.style.overflow = restore.overflow
  body.style.paddingRight = restore.paddingRight
  document.documentElement.style.removeProperty('--panel-scrollbar-width')
  restore = null
}

function applyScrollbarCompensation(body: HTMLElement): void {
  // Hiding the scrollbar narrows the viewport, which reflows the page behind
  // the overlay — visible as a sideways jump the moment a dialog opens. Pad the
  // body by exactly the width the scrollbar occupied.
  const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth
  if (scrollbarWidth <= 0) return

  const current = Number.parseFloat(window.getComputedStyle(body).paddingRight) || 0
  body.style.paddingRight = `${current + scrollbarWidth}px`
  document.documentElement.style.setProperty('--panel-scrollbar-width', `${scrollbarWidth}px`)
}

/**
 * Lock page scroll while an overlay is open.
 *
 * Returns a writable ref: set it to `true` to hold the lock, `false` to release.
 * The page only scrolls again once *every* holder has released, so stacked
 * overlays can each manage their own lock without fighting over `body.overflow`.
 */
export function useBodyScrollLock(initialState = false): Ref<boolean> {
  const holder = {}
  const locked = ref(initialState)

  watch(locked, (value) => {
    if (value) holders.add(holder)
    else holders.delete(holder)
    sync()
  }, { immediate: true })

  onScopeDispose(() => {
    holders.delete(holder)
    sync()
  })

  return locked
}
