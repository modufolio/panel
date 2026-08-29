import { computed, getCurrentScope, onScopeDispose, ref, type Ref } from 'vue'
import { reconcile } from '../Utils/reconcile'
import { isAbortError, type FetchContext } from './useAsyncData'

/**
 * Keyed server-state cache — `useAsyncData` plus sharing. Components that ask
 * for the same key share one `data` ref, one in-flight request and one cached
 * result, with stale-while-revalidate semantics: a new subscriber gets the
 * cached value instantly while a background refetch runs. Fresh payloads are
 * `reconcile`d into the cached object in place, so identity survives
 * revalidation and only effects reading changed fields re-run.
 *
 *   const counts = useQuery('library:counts',
 *     ({ signal }) => apiFetch('/panel/api/library/counts', { signal }))
 *
 * Keys are hierarchical by convention (`'tags:search:cats'`);
 * `invalidateQueries('tags')` marks `tags` and every `tags:*` entry stale and
 * refetches the ones with live subscribers. Call it after a mutation that
 * changes what a query would return.
 */

type Fetcher<T> = (ctx: FetchContext) => Promise<T>

interface QueryEntry<T = unknown> {
  data: Ref<T | null>
  error: Ref<unknown>
  fetching: Ref<boolean>
  /** Epoch ms of the last successful fetch; 0 = never fetched (or invalidated). */
  updatedAt: number
  /** Monotonic token: a superseded fetch may not touch entry state. */
  fetchId: number
  promise: Promise<void> | null
  controller: AbortController | null
  fetcher: Fetcher<T>
  subscribers: number
  /** Set by invalidateQueries() when a fetch is already in flight; consumed
   *  by runFetch's finally() to queue exactly one follow-up fetch. */
  pendingRefetch: boolean
}

const cache = new Map<string, QueryEntry>()

function runFetch(entry: QueryEntry): Promise<void> {
  if (entry.promise) return entry.promise

  const id = ++entry.fetchId
  const controller = new AbortController()
  entry.controller = controller
  entry.fetching.value = true

  const promise = entry
    .fetcher({ signal: controller.signal })
    .then((result) => {
      if (entry.fetchId !== id) return
      entry.data.value = reconcile(entry.data.value, result)
      entry.updatedAt = Date.now()
      entry.error.value = null
    })
    .catch((err) => {
      if (entry.fetchId !== id || isAbortError(err)) return
      entry.error.value = err
    })
    .finally(() => {
      if (entry.fetchId !== id) return
      entry.promise = null
      entry.controller = null
      entry.fetching.value = false

      // One or more invalidations arrived while this fetch was running —
      // run exactly one more fetch to pick up the latest state, instead of
      // each invalidation having aborted and restarted the request.
      if (entry.pendingRefetch) {
        entry.pendingRefetch = false
        entry.updatedAt = 0
        void runFetch(entry)
      }
    })

  entry.promise = promise
  return promise
}

export interface UseQueryOptions<T> {
  /**
   * How long (ms) a cached result is served without a background revalidation
   * when a new subscriber appears. Default 0: always revalidate (SWR).
   */
  staleTime?: number
  /** Seed value for `data` before the first successful fetch. */
  initialData?: T
}

export interface UseQueryReturn<T> {
  data: Ref<T | null>
  error: Ref<unknown>
  /** True only during the first fetch, before any data exists. */
  loading: Ref<boolean>
  /** True whenever a fetch (including a background revalidation) is running. */
  fetching: Ref<boolean>
  /** Fetch now (deduped against an already in-flight request). */
  refresh: () => Promise<void>
  /** Optimistically set the shared cached data (value or updater). */
  mutate: (updater: (T | null) | ((current: T | null) => T | null)) => void
}

export function useQuery<T>(
  key: string,
  fetcher: Fetcher<T>,
  options: UseQueryOptions<T> = {},
): UseQueryReturn<T> {
  const { staleTime = 0, initialData = null } = options

  let entry = cache.get(key) as QueryEntry<T> | undefined
  if (!entry) {
    entry = {
      data: ref(initialData) as Ref<T | null>,
      error: ref<unknown>(null),
      fetching: ref(false),
      updatedAt: 0,
      fetchId: 0,
      promise: null,
      controller: null,
      fetcher,
      subscribers: 0,
      pendingRefetch: false,
    }
    cache.set(key, entry as QueryEntry)
  } else {
    // Newest closure wins so revalidations don't run with captured stale state.
    entry.fetcher = fetcher
  }

  const shared = entry as QueryEntry<T>

  shared.subscribers++
  if (getCurrentScope()) {
    onScopeDispose(() => {
      shared.subscribers--
      // Nobody is watching: stop the request but keep the cached data so the
      // next subscriber gets an instant (stale-while-revalidate) render.
      if (shared.subscribers === 0) shared.controller?.abort()
    })
  }

  // `>=`, not `>`: with the default staleTime of 0 the two are only
  // distinguishable when a subscriber arrives in the *same millisecond* as
  // the last fetch — and there `>` reads as "still fresh", silently skipping
  // the revalidation that staleTime 0 promises. Fast machines hit it; slow
  // ones hide it, which is what makes it a flake rather than a failure.
  if (shared.updatedAt === 0 || Date.now() - shared.updatedAt >= staleTime) {
    void runFetch(shared as QueryEntry)
  }

  return {
    data: shared.data,
    error: shared.error,
    loading: computed(() => shared.fetching.value && shared.updatedAt === 0),
    fetching: shared.fetching,
    refresh: () => runFetch(shared as QueryEntry),
    mutate: (updater) => {
      shared.data.value = typeof updater === 'function'
        ? (updater as (current: T | null) => T | null)(shared.data.value)
        : updater
    },
  }
}

/**
 * Mark matching queries stale and refetch the ones that currently have
 * subscribers. `key` matches itself and everything below it (`key` and
 * `key:*`); omit it to invalidate everything.
 *
 * If a matching entry is already fetching, its in-flight request is left
 * alone (not aborted) and exactly one follow-up fetch is queued for once it
 * settles — so any number of invalidate calls arriving in a burst (e.g. one
 * per file in a batch upload, each firing on its own network completion)
 * coalesce into at most one extra request per entry, instead of each call
 * aborting and restarting the previous one. The returned promise resolves
 * once every matching entry's current fetch (in-flight or freshly started)
 * settles — for an entry that gets coalesced, that's the in-flight fetch,
 * not the queued follow-up; nothing in the app depends on finer-grained
 * timing than that.
 */
export function invalidateQueries(key?: string): Promise<void> {
  const refetches: Promise<void>[] = []

  for (const [entryKey, entry] of cache) {
    if (key !== undefined && entryKey !== key && !entryKey.startsWith(`${key}:`)) continue

    // Always mark stale, even without subscribers, so the next subscriber
    // revalidates instead of trusting a cached value that predates whatever
    // changed. Only entries with a live subscriber are actively refetched.
    entry.updatedAt = 0
    if (entry.subscribers === 0) continue

    if (entry.promise) {
      entry.pendingRefetch = true
      refetches.push(entry.promise)
    } else {
      refetches.push(runFetch(entry))
    }
  }

  return Promise.all(refetches).then(() => undefined)
}

/** Drop every cache entry. Intended for tests. */
export function clearQueryCache(): void {
  for (const entry of cache.values()) entry.controller?.abort()
  cache.clear()
}
