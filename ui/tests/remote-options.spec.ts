import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { effectScope } from 'vue'
import { useRemoteOptions } from '../src/Components/Fields/useRemoteOptions'

interface Option { value: string; label: string }

function respond(data: Option[], truncated = false) {
  return {
    ok: true,
    json: () => Promise.resolve({ data, meta: { truncated } }),
  }
}

let fetchMock: ReturnType<typeof vi.fn>

beforeEach(() => {
  vi.useFakeTimers()
  fetchMock = vi.fn().mockResolvedValue(respond([{ value: 'a', label: 'Anise' }]))
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

/** Run the composable inside a scope, the way a component would own it. */
function inScope<T>(build: () => T): { value: T; stop: () => void } {
  const scope = effectScope()
  const value = scope.run(build) as T

  return { value, stop: () => scope.stop() }
}

describe('useRemoteOptions', () => {
  it('debounces typing into one request and keeps the last term', async () => {
    const { value: remote } = inScope(() =>
      useRemoteOptions<Option>({ searchUrl: () => '/relations/ingredient' }),
    )

    remote.search('an')
    remote.search('ani')
    remote.search('anis')

    await vi.runAllTimersAsync()

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0][0]).toBe('/relations/ingredient?q=anis')
    expect(remote.results.value).toEqual([{ value: 'a', label: 'Anise' }])
    expect(remote.loading.value).toBe(false)
  })

  it('reports the server truncating the list', async () => {
    fetchMock.mockResolvedValue(respond([{ value: 'a', label: 'Anise' }], true))

    const { value: remote } = inScope(() =>
      useRemoteOptions<Option>({ searchUrl: () => '/relations/ingredient' }),
    )

    remote.search('a')
    await vi.runAllTimersAsync()

    expect(remote.truncated.value).toBe(true)
  })

  it('asks for held identifiers without touching the search results', async () => {
    const { value: remote } = inScope(() =>
      useRemoteOptions<Option>({ searchUrl: () => '/relations/ingredient?scope=all' }),
    )

    const labels = await remote.fetchValues('u-1,u-2')

    // The endpoint already carries a query string, so the parameter joins it.
    expect(fetchMock.mock.calls[0][0]).toBe('/relations/ingredient?scope=all&values=u-1%2Cu-2')
    expect(labels).toEqual([{ value: 'a', label: 'Anise' }])
    expect(remote.results.value).toEqual([])
  })

  it('feeds every batch to onReceive, searches and values alike', async () => {
    const received: Option[][] = []
    const { value: remote } = inScope(() =>
      useRemoteOptions<Option>({
        searchUrl: () => '/relations/ingredient',
        onReceive: (options) => received.push(options),
      }),
    )

    await remote.fetchValues('u-1')
    remote.search('a')
    await vi.runAllTimersAsync()

    expect(received).toHaveLength(2)
  })

  it('empties the list when the request fails, rather than throwing', async () => {
    fetchMock.mockResolvedValue({ ok: false, status: 500 })
    const errors = vi.spyOn(console, 'error').mockImplementation(() => {})

    const { value: remote } = inScope(() =>
      useRemoteOptions<Option>({ searchUrl: () => '/relations/ingredient' }),
    )

    remote.results.value = [{ value: 'stale', label: 'Stale' }]
    remote.search('a')
    await vi.runAllTimersAsync()

    expect(remote.results.value).toEqual([])
    expect(remote.loading.value).toBe(false)
    errors.mockRestore()
  })

  it('does not fetch without an endpoint', async () => {
    const { value: remote } = inScope(() => useRemoteOptions<Option>({ searchUrl: () => null }))

    remote.search('a')
    await vi.runAllTimersAsync()

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('drops a pending search when the owning scope stops', async () => {
    const { value: remote, stop } = inScope(() =>
      useRemoteOptions<Option>({ searchUrl: () => '/relations/ingredient' }),
    )

    remote.search('a')
    stop()
    await vi.runAllTimersAsync()

    expect(fetchMock).not.toHaveBeenCalled()
  })
})
