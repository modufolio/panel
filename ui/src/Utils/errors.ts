import { computed, type ComputedRef } from 'vue'
import { ApiError } from './apiFetch'

/**
 * The error bag as the server sends it.
 *
 * Appkit's `ValidationResult::errors()` keys every field to a *list* of
 * messages — a field can fail more than one constraint — while every field
 * component here takes a single `error` string, as does Inertia's own
 * convention for the shared `errors` prop. The two shapes meet here and
 * nowhere else: a page that reads the bag by hand ends up rendering
 * `["Enter a valid photo URL."]` into the message slot.
 */
export type ServerErrors = Record<string, string | string[] | null | undefined>

/**
 * One message per field: the first, which is the one a field has room to show.
 *
 * Fields with nothing to say are dropped rather than kept as an empty string,
 * so `errors.name` stays falsy and the control renders clean. A plain string
 * passes through untouched, for a server that sends the flat shape already.
 */
export function flattenErrors(errors: ServerErrors | undefined | null): Record<string, string> {
  const flat: Record<string, string> = {}

  for (const [field, message] of Object.entries(errors ?? {})) {
    const first = Array.isArray(message) ? message[0] : message

    if (typeof first === 'string' && first !== '') {
      flat[field] = first
    }
  }

  return flat
}

/**
 * Every message, in order — for a toast or a summary line, where the whole
 * list belongs rather than one field's first complaint.
 */
export function errorMessages(errors: ServerErrors | undefined | null): string[] {
  return Object.values(errors ?? {})
    .flatMap((message) => (Array.isArray(message) ? message : [message]))
    .filter((message): message is string => typeof message === 'string' && message !== '')
}

/**
 * The template-facing version: `useForm()`'s `errors` re-shaped, recomputed as
 * the server sets them and the user clears them.
 */
export function useFormErrors(source: { errors: ServerErrors }): ComputedRef<Record<string, string>> {
  return computed(() => flattenErrors(source.errors))
}

/**
 * What to tell the user about a rejected {@link ApiError}.
 *
 * A JSON endpoint refuses in one of three shapes: a validation bag (`errors`),
 * a single `error` string, or a `message`. Every caller that catches an
 * `ApiError` had to know all three; they ask here instead.
 *
 * Only the body is read. Whether the *status* deserves a word of its own is
 * already settled by `configureHttpErrors`, and a caller that catches
 * something which is not an `ApiError` — a network failure, a bug in its own
 * handler — gets the `fallback`, since neither has a server message to show.
 */
export function apiErrorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) {
    return fallback
  }

  const body = error.body as { errors?: ServerErrors; error?: unknown; message?: unknown } | null

  const messages = errorMessages(body?.errors)
  if (messages.length > 0) {
    return messages.join(' ')
  }

  if (typeof body?.error === 'string' && body.error !== '') {
    return body.error
  }

  if (typeof body?.message === 'string' && body.message !== '') {
    return body.message
  }

  return fallback
}
