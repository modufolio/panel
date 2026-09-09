import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

// Hoisted with the mock: a plain `const` here would be initialised after it.
const { flushAll } = vi.hoisted(() => ({ flushAll: vi.fn() }))
vi.mock('@inertiajs/vue3', () => ({ router: { flushAll } }))

import { useRelationship } from '../src/Components/Composables/useRelationship'
import { ApiError } from '../src/Utils/apiFetch'
import { setCsrfToken } from '../src/Utils/csrf'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(body === null ? '' : JSON.stringify(body), {
    status,
    headers: body === null ? {} : { 'content-type': 'application/json' },
  })
}

let fetchMock: ReturnType<typeof vi.fn>

beforeEach(() => {
  flushAll.mockClear()
  setCsrfToken('tok-1')
  // A fresh Response per call: a body can only be read once, and a write
  // refreshes the list straight after.
  fetchMock = vi.fn(async () => jsonResponse({ data: [] }))
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  vi.unstubAllGlobals()
})

function relationship() {
  return useRelationship({ endpoint: '/api/movies', relationship: 'actors' })
}

describe('useRelationship transport', () => {
  it('sends the CSRF token and JSON headers on a write', async () => {
    await relationship().attach('m1', 'a1')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/movies/m1/relationships/actors')
    expect(init.method).toBe('POST')
    expect(init.credentials).toBe('same-origin')
    expect(init.headers['X-CSRF-Token']).toBe('tok-1')
    expect(init.headers['Content-Type']).toBe('application/json')
    expect(init.headers['X-Requested-With']).toBe('XMLHttpRequest')
    expect(JSON.parse(init.body)).toEqual({ data: { type: 'actors', id: 'a1' } })
  })

  it('drops prefetched pages after a write, since they are now stale', async () => {
    await relationship().attach('m1', 'a1')

    expect(flushAll).toHaveBeenCalled()
  })

  it('leaves prefetched pages alone on a read', async () => {
    await relationship().fetchRecords()

    expect(flushAll).not.toHaveBeenCalled()
  })

  it('reads the list and its pagination meta', async () => {
    fetchMock.mockImplementation(async () =>
      jsonResponse({ data: [{ id: 'a1' }], meta: { total: 7, last_page: 2 } }),
    )

    const relation = relationship()
    await relation.fetchRecords()

    expect(relation.records.value).toEqual([{ id: 'a1' }])
    expect(relation.totalRecords.value).toBe(7)
    expect(relation.lastPage.value).toBe(2)
    expect(relation.loading.value).toBe(false)
  })

  it('throws an ApiError carrying the status and the server sentence', async () => {
    fetchMock.mockImplementation(async () =>
      jsonResponse({ message: 'That actor is already cast.' }, 409),
    )
    const errors = vi.spyOn(console, 'error').mockImplementation(() => {})

    await expect(relationship().attach('m1', 'a1')).rejects.toThrow(ApiError)
    await expect(relationship().attach('m1', 'a1')).rejects.toMatchObject({
      status: 409,
      message: 'That actor is already cast.',
    })

    errors.mockRestore()
  })

  it('resolves create() to an object even when the server answers empty', async () => {
    // 204 was the case the old hand-rolled parser special-cased; keep its answer.
    fetchMock.mockImplementation(async () => new Response('', { status: 204 }))

    await expect(relationship().create('m1', { name: 'Ada' })).resolves.toEqual({})
  })

  it('deletes without a body', async () => {
    await relationship().deleteRecord('a1')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/movies/a1')
    expect(init.method).toBe('DELETE')
    expect(init.body).toBeUndefined()
  })
})
