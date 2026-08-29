import { describe, it, expect, vi, beforeEach } from 'vitest'
import { effectScope } from 'vue'
import { useQuery, invalidateQueries, clearQueryCache } from '../src/index'

const flush = () => new Promise<void>((res) => setTimeout(res))

describe('useQuery', () => {
  beforeEach(() => {
    clearQueryCache()
  })

  it('shares one data ref and one in-flight request between subscribers', async () => {
    const fetcher = vi.fn(async () => ({ n: 1 }))
    const scope = effectScope()
    const [a, b] = scope.run(() => [
      useQuery('t:shared', fetcher),
      useQuery('t:shared', fetcher),
    ])!
    await flush()
    expect(fetcher).toHaveBeenCalledTimes(1)
    expect(a.data.value).toEqual({ n: 1 })
    expect(b.data.value).toBe(a.data.value)
    scope.stop()
  })

  it('serves cached data instantly and revalidates in the background', async () => {
    let n = 0
    const fetcher = vi.fn(async () => ({ n: ++n }))

    const first = effectScope()
    first.run(() => useQuery('t:swr', fetcher))
    await flush()
    first.stop()

    const second = effectScope()
    const query = second.run(() => useQuery('t:swr', fetcher))!
    // Cached value visible synchronously, before the revalidation lands.
    expect(query.data.value).toEqual({ n: 1 })
    expect(query.loading.value).toBe(false)
    expect(query.fetching.value).toBe(true)
    await flush()
    expect(query.data.value).toEqual({ n: 2 })
    second.stop()
  })

  it('respects staleTime: a fresh entry is not refetched', async () => {
    const fetcher = vi.fn(async () => ({ n: 1 }))
    const first = effectScope()
    first.run(() => useQuery('t:fresh', fetcher, { staleTime: 60_000 }))
    await flush()
    first.stop()

    const second = effectScope()
    second.run(() => useQuery('t:fresh', fetcher, { staleTime: 60_000 }))
    await flush()
    expect(fetcher).toHaveBeenCalledTimes(1)
    second.stop()
  })

  it('reconciles revalidated payloads in place, preserving row identity', async () => {
    const payloads = [
      { rows: [{ id: 1, v: 'a' }, { id: 2, v: 'b' }] },
      { rows: [{ id: 1, v: 'a' }, { id: 2, v: 'B' }] },
    ]
    let call = 0
    const scope = effectScope()
    const query = scope.run(() =>
      useQuery('t:reconcile', async () => payloads[call++]),
    )!
    await flush()
    const firstRow = query.data.value!.rows[0]

    await query.refresh()
    expect(query.data.value!.rows[0]).toBe(firstRow) // identity kept
    expect(query.data.value!.rows[1].v).toBe('B')    // change applied
    scope.stop()
  })

  it('invalidateQueries refetches subscribed queries, matching key prefixes', async () => {
    const counts = vi.fn(async () => ({ n: 1 }))
    const tags = vi.fn(async () => ['x'])
    const scope = effectScope()
    scope.run(() => {
      useQuery('library:counts', counts)
      useQuery('tags:all', tags)
    })
    await flush()

    await invalidateQueries('library')
    expect(counts).toHaveBeenCalledTimes(2)
    expect(tags).toHaveBeenCalledTimes(1)
    scope.stop()
  })

  it('invalidateQueries only marks unsubscribed queries stale', async () => {
    const fetcher = vi.fn(async () => ({ n: 1 }))
    const scope = effectScope()
    scope.run(() => useQuery('t:idle', fetcher, { staleTime: 60_000 }))
    await flush()
    scope.stop()

    await invalidateQueries('t:idle')
    expect(fetcher).toHaveBeenCalledTimes(1) // no refetch without subscribers

    // …but the next subscriber revalidates despite the long staleTime.
    const next = effectScope()
    next.run(() => useQuery('t:idle', fetcher, { staleTime: 60_000 }))
    await flush()
    expect(fetcher).toHaveBeenCalledTimes(2)
    next.stop()
  })

  it('aborts the in-flight request when the last subscriber leaves', () => {
    const signals: AbortSignal[] = []
    const scope = effectScope()
    scope.run(() =>
      useQuery('t:abort', ({ signal }) => {
        signals.push(signal)
        return new Promise(() => {})
      }),
    )
    scope.stop()
    expect(signals[0].aborted).toBe(true)
  })

  it('mutate applies an optimistic local edit to the shared entry', async () => {
    const scope = effectScope()
    const [a, b] = scope.run(() => [
      useQuery<{ n: number }>('t:mutate', async () => ({ n: 1 })),
      useQuery<{ n: number }>('t:mutate', async () => ({ n: 1 })),
    ])!
    await flush()
    a.mutate((current) => ({ n: (current?.n ?? 0) + 10 }))
    expect(b.data.value).toEqual({ n: 11 })
    scope.stop()
  })

  it('does not abort an in-flight fetch on invalidation — it queues one follow-up instead', async () => {
    const resolvers: Array<(v: { n: number }) => void> = []
    const fetcher = vi.fn(() => new Promise<{ n: number }>((res) => resolvers.push(res)))
    const scope = effectScope()
    const query = scope.run(() => useQuery('t:race', fetcher))!

    // Invalidate while the initial fetch is still in flight.
    void invalidateQueries('t:race')
    expect(fetcher).toHaveBeenCalledTimes(1) // not aborted/restarted

    resolvers[0]({ n: 1 }) // in-flight fetch settles
    await flush()
    expect(fetcher).toHaveBeenCalledTimes(2) // coalesced follow-up now fires
    expect(query.data.value).toEqual({ n: 1 })

    resolvers[1]({ n: 2 })
    await flush()
    expect(query.data.value).toEqual({ n: 2 })
    scope.stop()
  })

  it('coalesces a burst of invalidations arriving mid-flight into a single follow-up fetch', async () => {
    const resolvers: Array<(v: { n: number }) => void> = []
    const fetcher = vi.fn(() => new Promise<{ n: number }>((res) => resolvers.push(res)))
    const scope = effectScope()
    scope.run(() => useQuery('t:burst', fetcher))

    // Simulate a batch of files completing in rapid succession — many
    // invalidations while the first fetch is still in flight.
    for (let i = 0; i < 10; i++) void invalidateQueries('t:burst')
    expect(fetcher).toHaveBeenCalledTimes(1)

    resolvers[0]({ n: 1 })
    await flush()
    // Exactly one follow-up, not ten.
    expect(fetcher).toHaveBeenCalledTimes(2)

    resolvers[1]({ n: 2 })
    await flush()
    scope.stop()
  })
})
