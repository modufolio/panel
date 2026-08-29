import { getCurrentInstance, useId as vueUseId } from 'vue'

/** Only reached outside a component instance — see useId(). */
let fallbackCount = 0

/**
 * A DOM id for wiring ARIA relationships (`aria-labelledby`, `aria-controls`, …).
 *
 * Prefers Vue's own `useId()`, which is app-scoped and produces the same value
 * on a server render and its hydration — `Math.random()` does not, and a panel
 * page rendered on the server would hydrate with mismatched ids. Vue's version
 * needs a component instance, so anything called outside one (a bare composable
 * in a test) falls back to a module counter.
 *
 * @param deterministicId Caller-supplied id, returned untouched when present,
 *   so a component can accept an `id` prop and still have a default.
 */
export function useId(deterministicId?: string | null, prefix = 'panel'): string {
  if (deterministicId) return deterministicId

  const id = getCurrentInstance() && typeof vueUseId === 'function'
    ? vueUseId()
    : undefined

  return `${prefix}-${id ?? ++fallbackCount}`
}
