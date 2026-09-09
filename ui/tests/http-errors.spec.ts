import { describe, it, expect, vi, beforeEach } from 'vitest'

const showToast = vi.fn()
vi.mock('../src/Components/Notifications/pageToasts', () => ({ showToast }))

const { configureHttpErrors, httpErrorFor, httpErrorMessage, notifyHttpError, notifyPrefetchedError } = await import('../src/Components/Notifications/httpErrors')
const { useErrorModal, closeErrorModal } = await import('../src/Components/Notifications/errorModal')

const modal = () => useErrorModal().state.value

/**
 * A failed status maps to one sentence, declared once, and to how it is
 * said: a toast for what the viewer can shrug off, a modal for what ends
 * what they were doing. A status mapped to `false` is left to whoever else
 * handles it, and any 5xx nobody named gets the server entry.
 */
describe('HTTP error messages', () => {
  beforeEach(() => {
    showToast.mockClear()
    closeErrorModal()
    configureHttpErrors()
  })

  it('knows the statuses a panel meets, and leaves validation to the forms', () => {
    expect(httpErrorMessage(419)).toMatch(/session expired/i)
    expect(httpErrorMessage(403)).toMatch(/not allowed/i)
    expect(httpErrorMessage(422)).toBeUndefined()
    expect(httpErrorMessage(418)).toBeUndefined()
    expect(httpErrorMessage(507)).toBe(httpErrorMessage(500))
  })

  it('shows a toast and reports it, so the caller can drop its own fallback', () => {
    expect(notifyHttpError(419)).toBe(true)
    expect(showToast).toHaveBeenCalledWith({ type: 'warning', message: expect.stringMatching(/session expired/i) })

    expect(notifyHttpError(422)).toBe(false)
    expect(showToast).toHaveBeenCalledTimes(1)
  })

  it('opens the modal for a failure that ends what the viewer was doing', () => {
    // A dead session, a refused action and a broken server are not notices:
    // each one stops the user, and a toast fading on a timer is missed.
    expect(httpErrorFor(401)?.as).toBe('modal')
    expect(httpErrorFor(403)?.as).toBe('modal')
    expect(httpErrorFor(507)?.as).toBe('modal')

    expect(notifyHttpError(500)).toBe(true)
    expect(showToast).not.toHaveBeenCalled()
    expect(modal()).toMatchObject({ open: true, status: 500, title: 'Something went wrong' })
  })

  it('prefers the server\'s own words when the response carried them', () => {
    notifyHttpError(500, { title: 'Storage unavailable', detail: 'The image store did not answer.' })

    expect(modal()).toMatchObject({
      title: 'Storage unavailable',
      message: 'The image store did not answer.',
    })
  })

  it('reports a prefetch that failed, and ignores one that did not', () => {
    // Inertia fires `prefetched` for every prefetch and never `httpException`
    // for a failed one, so this is the only place a broken prefetch is seen.
    expect(notifyPrefetchedError(200)).toBe(false)
    expect(notifyPrefetchedError(undefined)).toBe(false)
    expect(notifyPrefetchedError(422)).toBe(false)
    expect(showToast).not.toHaveBeenCalled()

    expect(notifyPrefetchedError(500)).toBe(true)
    expect(modal()).toMatchObject({ open: true, status: 500 })
  })

  it('takes the application\'s own sentences, and lets it silence a status', () => {
    configureHttpErrors({ 403: 'Ask an admin for access.', 500: false })

    // A plain sentence is a toast, which is also how an app opts a modal
    // status back out of the dialog.
    expect(httpErrorFor(403)).toEqual({ as: 'toast', message: 'Ask an admin for access.' })
    expect(httpErrorMessage(403)).toBe('Ask an admin for access.')
    expect(httpErrorMessage(500)).toBeUndefined()
    expect(httpErrorMessage(503)).toMatch(/busy/i)
    expect(httpErrorMessage(419)).toMatch(/session expired/i)
  })

  it('lets an application promote a status to a modal of its own', () => {
    configureHttpErrors({ 409: { as: 'modal', title: 'Out of date', message: 'Reload and try again.' } })

    expect(notifyHttpError(409)).toBe(true)
    expect(showToast).not.toHaveBeenCalled()
    expect(modal()).toMatchObject({ open: true, title: 'Out of date', message: 'Reload and try again.' })
  })
})
