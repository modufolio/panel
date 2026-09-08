import { describe, it, expect, vi, beforeEach } from 'vitest'

const showToast = vi.fn()
vi.mock('../src/Components/Notifications/pageToasts', () => ({ showToast }))

const { configureHttpErrors, httpErrorMessage, notifyHttpError } = await import('../src/Components/Notifications/httpErrors')

/**
 * A failed status maps to one sentence, declared once; a status mapped to
 * `false` is left to whoever else handles it, and any 5xx nobody named
 * gets the server sentence.
 */
describe('HTTP error messages', () => {
  beforeEach(() => {
    showToast.mockClear()
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

    expect(notifyHttpError(500)).toBe(true)
    expect(showToast).toHaveBeenLastCalledWith({ type: 'error', message: expect.any(String) })

    expect(notifyHttpError(422)).toBe(false)
    expect(showToast).toHaveBeenCalledTimes(2)
  })

  it('takes the application\'s own sentences, and lets it silence a status', () => {
    configureHttpErrors({ 403: 'Ask an admin for access.', 500: false })

    expect(httpErrorMessage(403)).toBe('Ask an admin for access.')
    expect(httpErrorMessage(500)).toBeUndefined()
    expect(httpErrorMessage(503)).toMatch(/busy/i)
    expect(httpErrorMessage(419)).toMatch(/session expired/i)
  })
})
