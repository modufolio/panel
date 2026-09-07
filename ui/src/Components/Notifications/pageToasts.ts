import { useToastStore } from './useToast'

/** One message the server wrote for this response, as `_toasts` carries it. */
export interface PageToast {
  type: string
  message: string
}

const KNOWN_TYPES = new Set(['success', 'error', 'warning', 'info'])

/** Show a server toast; an unknown type (a host's own flash key) reads as info. */
export function showToast(entry: PageToast): void {
  if (!entry || typeof entry.message !== 'string' || entry.message === '') return

  const type = KNOWN_TYPES.has(entry.type) ? (entry.type as PageToast['type'] & ('success' | 'error' | 'warning' | 'info')) : 'info'

  useToastStore().add({ type, message: entry.message })
}

/**
 * Show whatever `_toasts` a JSON reply carries. The server drains its flash
 * bag into JSON replies so a caller that never navigates still hears it.
 */
export function showToastsIn(body: unknown): void {
  if (!body || typeof body !== 'object') return

  const toasts = (body as { _toasts?: unknown })._toasts
  if (!Array.isArray(toasts)) return

  for (const entry of toasts) showToast(entry as PageToast)
}
