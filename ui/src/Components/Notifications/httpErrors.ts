import { showToast } from './pageToasts'
import { showErrorModal } from './errorModal'

/**
 * How a failed status is reported: a toast that fades on its own, or a modal
 * the viewer has to dismiss. `{ as: 'modal' }` is for a failure that ends
 * whatever the user was doing — a dead session, a refused action, a server
 * that broke — where a toast fading on a timer is too easy to miss.
 */
export interface HttpErrorPresentation {
  as: 'toast' | 'modal'
  /** Heading, modal only. Falls back to the server's own error title. */
  title?: string
  message: string
}

/**
 * What to tell the viewer when a request fails with a status and nothing
 * better to say: one entry per status, declared once. A bare string is a
 * toast with that sentence; `false` leaves the status to whatever else
 * handles it — 422 is the forms' business, and the Inertia error modal keeps
 * a status nobody mapped.
 *
 * Configured through `createPanel({ errorMessages })`; the defaults cover
 * the statuses a panel meets in practice.
 */
export type HttpErrorMessages = Partial<Record<number, string | false | HttpErrorPresentation>>

/** What the server's own JSON:API error body said, when it sent one. */
export interface ServerError {
  title?: string
  detail?: string
}

const DEFAULT_MESSAGES: HttpErrorMessages = {
  // A session that ended, an action refused, a server that broke: each one
  // stops the user where they stand, so each one is a modal rather than a
  // notice that disappears while they are looking elsewhere.
  401: { as: 'modal', title: 'Session expired', message: 'Your session has ended. Sign in again.' },
  403: { as: 'modal', title: 'Access denied', message: 'You are not allowed to do that.' },
  404: 'That no longer exists.',
  409: 'The page is out of date. Reload and try again.',
  419: 'Your session expired. Reload the page and try again.',
  422: false,
  429: 'Too many requests. Wait a moment and try again.',
  500: { as: 'modal', title: 'Something went wrong', message: 'Something went wrong on the server.' },
  502: { as: 'modal', title: 'Something went wrong', message: 'The server is not responding. Try again in a moment.' },
  503: { as: 'modal', title: 'Something went wrong', message: 'The server is busy. Try again in a moment.' },
  504: { as: 'modal', title: 'Something went wrong', message: 'The server took too long. Try again in a moment.' },
}

let messages: HttpErrorMessages = { ...DEFAULT_MESSAGES }

export function configureHttpErrors(overrides: HttpErrorMessages = {}): void {
  messages = { ...DEFAULT_MESSAGES, ...overrides }
}

/** Normalise a configured entry; `false` and an absent status give undefined. */
function presentationOf(entry: string | false | HttpErrorPresentation | undefined): HttpErrorPresentation | undefined {
  if (entry === false || entry === undefined) return undefined
  if (typeof entry === 'string') return { as: 'toast', message: entry }

  return entry
}

/** How a status is reported, or undefined when it is left alone. */
export function httpErrorFor(status: number): HttpErrorPresentation | undefined {
  const own = presentationOf(messages[status])
  if (own) return own
  if (messages[status] === false) return undefined

  // An unmapped 5xx still deserves the generic server entry.
  if (status >= 500 && status <= 599) return presentationOf(messages[500])

  return undefined
}

/** The configured sentence for a status; undefined when the status is left alone. */
export function httpErrorMessage(status: number): string | undefined {
  return httpErrorFor(status)?.message
}

/**
 * Report a failed status. Returns whether anything was shown, so a caller can
 * decide whether to also suppress its own fallback (the Inertia error modal,
 * a console dump).
 *
 * `serverError` is the response's own JSON:API error object when it sent one.
 * The server already decides how much of it is safe to show — its `detail` is
 * redacted outside dev — so whatever arrives is displayed as-is, and the
 * client needs no environment check of its own.
 */
export function notifyHttpError(status: number, serverError?: ServerError): boolean {
  const error = httpErrorFor(status)
  if (!error) return false

  if (error.as === 'modal') {
    showErrorModal({
      status,
      title: serverError?.title || error.title || 'Something went wrong',
      message: serverError?.detail || error.message,
    })

    return true
  }

  showToast({ type: status >= 500 ? 'error' : 'warning', message: error.message })

  return true
}

/**
 * A prefetched response that came back failed. Inertia fires `prefetched`
 * for every prefetch, success or failure, and returns before its exception
 * handling — a prefetch warms the cache silently, and the failure would only
 * surface on the click that replays it. A 500 from broken server wiring does
 * not fix itself by then, so it is reported now; a response without a
 * status, or one that succeeded, is left alone. Returns whether anything was
 * shown.
 */
export function notifyPrefetchedError(status: number | undefined, serverError?: ServerError): boolean {
  if (status === undefined || status < 400) return false

  return notifyHttpError(status, serverError)
}

/** The connection failed before any status arrived. */
export function notifyNetworkError(): void {
  showToast({ type: 'error', message: 'Could not reach the server. Check your connection and try again.' })
}
