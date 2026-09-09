import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

// Hoisted with the mock: a plain `const` here would be initialised after it.
const { flushAll } = vi.hoisted(() => ({ flushAll: vi.fn() }))
vi.mock('@inertiajs/vue3', () => ({ router: { flushAll } }))

import { useDeleteConfirmation } from '../src/Composables/useDeleteConfirmation'

interface Record_ { id: string }

let fetchMock: ReturnType<typeof vi.fn>

beforeEach(() => {
  fetchMock = vi.fn(async () => new Response(
    JSON.stringify({ blocked: false, counts: { reviews: 3 } }),
    { status: 200, headers: { 'content-type': 'application/json' } },
  ))
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  vi.unstubAllGlobals()
})

function confirmation(previewUrl?: (record: Record_) => string) {
  return useDeleteConfirmation<Record_>({ previewUrl, onConfirm: vi.fn() })
}

describe('delete preview', () => {
  it('asks the server what the delete would take with it', async () => {
    const dialog = confirmation((record) => `/panel/movies/${record.id}/delete-preview`)

    await dialog.request({ id: 'm1' })

    expect(fetchMock.mock.calls[0][0]).toBe('/panel/movies/m1/delete-preview')
    expect(dialog.state.plan).toEqual({ blocked: false, counts: { reviews: 3 } })
    expect(dialog.state.loading).toBe(false)
    expect(dialog.state.open).toBe(true)
  })

  it('refuses rather than guesses when the preview fails', async () => {
    fetchMock.mockImplementation(async () => new Response('{}', { status: 500 }))
    const errors = vi.spyOn(console, 'error').mockImplementation(() => {})

    const dialog = confirmation((record) => `/panel/movies/${record.id}/delete-preview`)
    await dialog.request({ id: 'm1' })

    expect(dialog.state.plan).toEqual({
      blocked: true,
      protected: ['Could not check what depends on this record.'],
    })
    expect(dialog.state.loading).toBe(false)
    errors.mockRestore()
  })

  it('does not ask at all without a preview endpoint', async () => {
    const dialog = confirmation()

    await dialog.request({ id: 'm1' })

    expect(fetchMock).not.toHaveBeenCalled()
    expect(dialog.state.open).toBe(true)
  })
})
