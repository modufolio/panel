import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { apiFetch, ApiError } from '../src/index'
import { setCsrfToken } from '../src/index'

function mockResponse(body: unknown, { status = 200, json = true } = {}): Response {
  const text = typeof body === 'string' ? body : JSON.stringify(body)
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: (h: string) => (h.toLowerCase() === 'content-type' && json ? 'application/json' : '') },
    text: () => Promise.resolve(text),
  } as unknown as Response
}

describe('apiFetch', () => {
  beforeEach(() => {
    setCsrfToken('tok123')
    vi.stubGlobal('fetch', vi.fn())
  })
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('attaches the CSRF token and JSON headers, and encodes an object body', async () => {
    ;(fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValue(mockResponse({ ok: true }))

    await apiFetch('/panel/api/x', { method: 'PATCH', body: { a: 1 } })

    const [url, init] = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0]
    expect(url).toBe('/panel/api/x')
    expect(init.method).toBe('PATCH')
    expect(init.headers['X-CSRF-Token']).toBe('tok123')
    expect(init.headers['Content-Type']).toBe('application/json')
    expect(init.body).toBe(JSON.stringify({ a: 1 }))
  })

  it('returns the parsed JSON body on success', async () => {
    ;(fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValue(mockResponse({ hello: 'world' }))
    const data = await apiFetch<{ hello: string }>('/x')
    expect(data.hello).toBe('world')
  })

  it('returns null for a 204 response', async () => {
    ;(fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValue(mockResponse('', { status: 204 }))
    expect(await apiFetch('/x', { method: 'DELETE' })).toBeNull()
  })

  it('throws ApiError carrying status and body on non-2xx', async () => {
    ;(fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValue(
      mockResponse({ message: 'Nope' }, { status: 422 }),
    )
    await expect(apiFetch('/x', { method: 'POST', body: {} })).rejects.toMatchObject({
      name: 'ApiError',
      status: 422,
    })
    try {
      await apiFetch('/x', { method: 'POST', body: {} })
    } catch (e) {
      expect(e).toBeInstanceOf(ApiError)
      expect((e as ApiError).message).toBe('Nope')
      expect((e as ApiError).body).toEqual({ message: 'Nope' })
    }
  })

  it('does not send a body or content-type for a GET', async () => {
    ;(fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValue(mockResponse([]))
    await apiFetch('/x')
    const [, init] = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0]
    expect(init.body).toBeUndefined()
    expect(init.headers['Content-Type']).toBeUndefined()
    expect(init.headers.Accept).toBe('application/json')
  })
})
