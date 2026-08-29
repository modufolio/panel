import { ref, onScopeDispose, watch, type Ref, type WatchSource } from 'vue'

/**
 * Reactive async-data primitive — the panel's standard way to run a fetch and
 * expose its lifecycle. Inspired by Solid's `createResource`: one place owns
 * `{ data, loading, error }`, requests are guarded against stale/post-teardown
 * resolution, and cleanup is automatic via the current effect scope.
 *
 * The fetcher receives a `{ signal }` context first (pass it to `apiFetch` so
 * a superseded or torn-down request is actually cancelled, not just ignored),
 * then whatever arguments `execute` was called with:
 *
 *   const { data, loading, error, execute } = useAsyncData(
 *     ({ signal }, id: number) => apiFetch(`/panel/api/tags/media/${id}`, { signal }),
 *   )
 *   await execute(mediaId)
 *
 * Errors are captured on `error` (and passed to `onError`) rather than thrown,
 * so callers can branch on `error.value` without a try/catch at every site.
 * An abort is not an error: it never touches `error` or `onError`.
 */
/** Per-request context handed to the fetcher. */
export interface FetchContext {
  /** Aborted when the request is superseded or the owning scope is disposed. */
  signal: AbortSignal
}

export function isAbortError(err: unknown): boolean {
  return err instanceof DOMException && err.name === 'AbortError'
}

export interface UseAsyncDataOptions<T> {
  /** Run the fetcher once on creation (with no arguments). Default false. */
  immediate?: boolean
  /** Seed value for `data` before the first successful fetch. */
  initialData?: T | null
  /** Refetch (re-running the last call) whenever any source changes. */
  watch?: WatchSource | WatchSource[]
  /** Called with the error when a fetch rejects. */
  onError?: (error: unknown) => void
}

export interface UseAsyncData<T, A extends unknown[]> {
  data: Ref<T | null>
  error: Ref<unknown>
  loading: Ref<boolean>
  /** Run the fetcher with the given args; resolves with the data (or null on error). */
  execute: (...args: A) => Promise<T | null>
  /** Re-run the fetcher with the arguments from the last `execute` call. */
  refresh: () => Promise<T | null>
  /** Optimistically set `data` locally (value or updater), e.g. before a write. */
  mutate: (updater: (T | null) | ((current: T | null) => T | null)) => void
}

export function useAsyncData<T, A extends unknown[] = []>(
  fetcher: (ctx: FetchContext, ...args: A) => Promise<T>,
  options: UseAsyncDataOptions<T> = {},
): UseAsyncData<T, A> {
  const { immediate = false, initialData = null, onError } = options

  const data = ref(initialData) as Ref<T | null>
  const error = ref<unknown>(null)
  const loading = ref(false)

  // Monotonic token so a superseded or post-teardown response is discarded
  // instead of clobbering newer state.
  let token = 0
  let disposed = false
  let lastArgs = [] as unknown as A
  let controller: AbortController | null = null

  const execute = async (...args: A): Promise<T | null> => {
    lastArgs = args
    const current = ++token
    controller?.abort()
    controller = new AbortController()
    loading.value = true
    error.value = null

    try {
      const result = await fetcher({ signal: controller.signal }, ...args)
      if (disposed || current !== token) return data.value
      data.value = result
      return result
    } catch (err) {
      if (disposed || current !== token || isAbortError(err)) return data.value
      error.value = err
      onError?.(err)
      return null
    } finally {
      if (!disposed && current === token) loading.value = false
    }
  }

  const refresh = () => execute(...lastArgs)

  const mutate = (updater: (T | null) | ((current: T | null) => T | null)) => {
    data.value = typeof updater === 'function'
      ? (updater as (current: T | null) => T | null)(data.value)
      : updater
  }

  onScopeDispose(() => {
    disposed = true
    controller?.abort()
  })

  if (options.watch) {
    watch(options.watch, () => {
      void refresh()
    })
  }

  if (immediate) {
    void execute(...([] as unknown as A))
  }

  return { data, error, loading, execute, refresh, mutate }
}
