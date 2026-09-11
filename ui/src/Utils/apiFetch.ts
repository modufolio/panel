/**
 * Small wrapper around `fetch` for the panel's JSON API calls.
 *
 * It centralises the three things every hand-rolled `fetch` in the panel used
 * to do (inconsistently): attach the CSRF token, send/parse JSON, and turn a
 * non-2xx response into a thrown error carrying the parsed body. Prefer this
 * over calling `fetch` directly so CSRF and error handling stay uniform.
 *
 *   const data = await apiFetch(panelUrl('/api/media/bulk'), {
 *     method: 'DELETE',
 *     body: { media_ids: ids },
 *   })
 */

import { router } from '@inertiajs/vue3'
import { getCsrfToken } from './csrf'
import { realtimeClientId, REALTIME_CLIENT_HEADER } from '../Composables/useRealtime'
import { showToastsIn } from '../Components/Notifications/pageToasts'
import { httpErrorMessage, notifyHttpError, type ServerError } from '../Components/Notifications/httpErrors'

export interface ApiFetchOptions extends Omit<RequestInit, 'body'> {
  /** Plain objects are JSON-encoded; strings/FormData/Blob are sent as-is. */
  body?: unknown
}

/** Thrown on a non-2xx response. `body` holds the parsed error payload. */
export class ApiError extends Error {
  readonly status: number
  readonly body: unknown

  constructor(message: string, status: number, body: unknown) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

function hasHeader(headers: Record<string, string>, name: string): boolean {
  return Object.keys(headers).some((key) => key.toLowerCase() === name.toLowerCase())
}

async function parseBody(response: Response): Promise<unknown> {
  if (response.status === 204 || response.status === 205) return null

  const text = await response.text()
  if (!text) return null

  const contentType = response.headers.get('content-type') || ''
  if (contentType.includes('application/json')) {
    try {
      return JSON.parse(text)
    } catch {
      return text
    }
  }
  return text
}

function isPlainBody(body: unknown): boolean {
  return (
    typeof body === 'string' ||
    body instanceof FormData ||
    body instanceof Blob ||
    body instanceof ArrayBuffer ||
    body instanceof URLSearchParams
  )
}

/**
 * Perform a JSON API request. Resolves with the parsed response body (or null
 * for an empty/204 response) and rejects with an {@link ApiError} on non-2xx.
 */
export async function apiFetch<T = unknown>(url: string, options: ApiFetchOptions = {}): Promise<T> {
  const { body, headers, ...rest } = options

  const finalHeaders: Record<string, string> = {
    Accept: 'application/json',
    // Announces the call as an XHR, which is what the security layer reads to
    // decide that a rejected request wants data back rather than an HTML error
    // page. Every hand-rolled fetch in the panel sent it; sending it here is
    // what lets them stop hand-rolling.
    'X-Requested-With': 'XMLHttpRequest',
    ...(headers as Record<string, string> | undefined),
  }

  let finalBody: BodyInit | undefined
  if (body !== undefined && body !== null) {
    if (isPlainBody(body)) {
      finalBody = body as BodyInit
    } else {
      finalBody = JSON.stringify(body)
      if (!hasHeader(finalHeaders, 'content-type')) {
        finalHeaders['Content-Type'] = 'application/json'
      }
    }
  }

  const token = getCsrfToken()
  if (token && !hasHeader(finalHeaders, 'x-csrf-token')) {
    finalHeaders['X-CSRF-Token'] = token
  }

  // Says which tab is writing, so the realtime nudge this causes can be
  // ignored by that tab — it already has the answer. Absent when the panel has
  // no realtime connection, which is the common case for a public deployment.
  const client = realtimeClientId()
  if (client !== null && !hasHeader(finalHeaders, REALTIME_CLIENT_HEADER)) {
    finalHeaders[REALTIME_CLIENT_HEADER] = client
  }

  const response = await fetch(url, {
    credentials: 'same-origin',
    ...rest,
    headers: finalHeaders,
    body: finalBody,
  })

  const payload = await parseBody(response)

  // Whatever the server flashed for this call is shown here, since a JSON
  // caller never loads the page that would otherwise carry it.
  showToastsIn(payload)

  // A write makes every prefetched page a stale snapshot.
  if (response.ok && (rest.method ?? 'GET').toUpperCase() !== 'GET') {
    router.flushAll()
  }

  if (!response.ok) {
    const serverError: ServerError | undefined =
      payload && typeof payload === 'object' && 'errors' in payload && Array.isArray((payload as { errors?: unknown }).errors)
        ? ((payload as { errors: unknown[] }).errors[0] as ServerError | undefined)
        : undefined

    notifyHttpError(response.status, serverError)

    const message =
      payload && typeof payload === 'object' && 'message' in payload &&
      typeof (payload as { message?: unknown }).message === 'string'
        ? (payload as { message: string }).message
        : httpErrorMessage(response.status) ?? `Request failed with status ${response.status}`
    throw new ApiError(message, response.status, payload)
  }

  return payload as T
}
