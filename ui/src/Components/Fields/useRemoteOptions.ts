import { ref, onScopeDispose, type Ref } from 'vue'

/**
 * A relation's options fetched from the server, for a field whose list is too
 * large to preload.
 *
 * The endpoint answers two questions, and both are asked here: `?q=` for what
 * matches what the user is typing, and `?values=` for the labels of
 * identifiers the field already holds — the second is what lets an edit form
 * show its current selection when the list it came from was never sent.
 *
 * Typing is debounced, because a request per keystroke is a request per
 * keystroke. `truncated` reports the server saying it cut the list short, so
 * a field can tell the user to keep typing rather than imply it showed
 * everything.
 *
 * Fetches with a bare `fetch` rather than `apiFetch`: a lookup failing is a
 * list that stays empty, not something to toast at the user or throw through
 * the component. The two fields that use this both wanted that, and this is
 * now the one place to revisit it.
 */

export interface RemoteOptionsOptions<T> {
  /** The endpoint, read on each call: a field may be given one after mount. */
  searchUrl: () => string | null | undefined
  /** Called with every batch the server returns, search and values alike. */
  onReceive?: (options: T[]) => void
  /** Milliseconds to wait after the last keystroke. */
  debounceMs?: number
}

export interface RemoteOptions<T> {
  /** What the last search returned. Writable: a field may seed it itself. */
  results: Ref<T[]>
  loading: Ref<boolean>
  /** The server said it cut the list short. */
  truncated: Ref<boolean>
  /** Debounced: search for a term, replacing `results` when it lands. */
  search: (term: string) => void
  /** The labels for identifiers already held. Returns them, assigns nothing. */
  fetchValues: (values: string) => Promise<T[]>
}

export function useRemoteOptions<T>({
  searchUrl,
  onReceive,
  debounceMs = 200,
}: RemoteOptionsOptions<T>): RemoteOptions<T> {
  const results = ref<T[]>([]) as Ref<T[]>
  const loading = ref(false)
  const truncated = ref(false)

  async function request(params: string): Promise<T[]> {
    const endpoint = searchUrl()
    if (!endpoint) return []

    const url = `${endpoint}${endpoint.includes('?') ? '&' : '?'}${params}`

    const response = await fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })

    if (!response.ok) {
      throw new Error(`Relation search failed with status ${response.status}`)
    }

    const body: { data?: unknown; meta?: { truncated?: unknown } } | null = await response.json()
    truncated.value = body?.meta?.truncated === true

    const options: T[] = Array.isArray(body?.data) ? (body.data as T[]) : []
    onReceive?.(options)

    return options
  }

  let timer: ReturnType<typeof setTimeout> | undefined

  function search(term: string): void {
    if (timer !== undefined) {
      clearTimeout(timer)
    }

    timer = setTimeout(async () => {
      loading.value = true
      try {
        results.value = await request(`q=${encodeURIComponent(term)}`)
      } catch (error) {
        console.error(error)
        results.value = []
      } finally {
        loading.value = false
      }
    }, debounceMs)
  }

  function fetchValues(values: string): Promise<T[]> {
    return request(`values=${encodeURIComponent(values)}`)
  }

  onScopeDispose(() => {
    if (timer !== undefined) {
      clearTimeout(timer)
    }
  })

  return { results, loading, truncated, search, fetchValues }
}
