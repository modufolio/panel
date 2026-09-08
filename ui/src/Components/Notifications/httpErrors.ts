import { showToast } from './pageToasts'

/**
 * What to tell the viewer when a request fails with a status and nothing
 * better to say: one sentence per status, declared once. `false` leaves a
 * status to whatever else handles it — 422 is the forms' business, and the
 * Inertia error modal keeps a status nobody mapped.
 *
 * Configured through `createPanel({ errorMessages })`; the defaults cover
 * the statuses a panel meets in practice.
 */
export type HttpErrorMessages = Partial<Record<number, string | false>>

const DEFAULT_MESSAGES: HttpErrorMessages = {
  401: 'Your session has ended. Sign in again.',
  403: 'You are not allowed to do that.',
  404: 'That no longer exists.',
  409: 'The page is out of date. Reload and try again.',
  419: 'Your session expired. Reload the page and try again.',
  422: false,
  429: 'Too many requests. Wait a moment and try again.',
  500: 'Something went wrong on the server.',
  502: 'The server is not responding. Try again in a moment.',
  503: 'The server is busy. Try again in a moment.',
  504: 'The server took too long. Try again in a moment.',
}

let messages: HttpErrorMessages = { ...DEFAULT_MESSAGES }

export function configureHttpErrors(overrides: HttpErrorMessages = {}): void {
  messages = { ...DEFAULT_MESSAGES, ...overrides }
}

/** The configured sentence for a status; undefined when the status is left alone. */
export function httpErrorMessage(status: number): string | undefined {
  const own = messages[status]
  if (own === false) return undefined
  if (typeof own === 'string') return own

  // An unmapped 5xx still deserves the generic server sentence.
  if (status >= 500 && status <= 599) {
    const generic = messages[500]
    return typeof generic === 'string' ? generic : undefined
  }

  return undefined
}

/**
 * Show the toast for a failed status. Returns whether one was shown, so a
 * caller can decide whether to also suppress its own fallback (the Inertia
 * error modal, a console dump).
 */
export function notifyHttpError(status: number): boolean {
  const message = httpErrorMessage(status)
  if (!message) return false

  showToast({ type: status >= 500 ? 'error' : 'warning', message })

  return true
}

/** The connection failed before any status arrived. */
export function notifyNetworkError(): void {
  showToast({ type: 'error', message: 'Could not reach the server. Check your connection and try again.' })
}
