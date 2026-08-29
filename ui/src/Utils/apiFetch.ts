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

import { getCsrfToken } from './csrf'

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

  const response = await fetch(url, {
    credentials: 'same-origin',
    ...rest,
    headers: finalHeaders,
    body: finalBody,
  })

  const payload = await parseBody(response)

  if (!response.ok) {
    const message =
      payload && typeof payload === 'object' && 'message' in payload &&
      typeof (payload as { message?: unknown }).message === 'string'
        ? (payload as { message: string }).message
        : `Request failed with status ${response.status}`
    throw new ApiError(message, response.status, payload)
  }

  return payload as T
}
