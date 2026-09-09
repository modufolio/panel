import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { apiFetch } from '../../Utils/apiFetch'
import { panelUrl } from '../../Utils/url'

/**
 * The panel's search across resources, for the dialog: a debounced query
 * to the server, results grouped by resource, and a cursor the arrow keys
 * move through every hit in order.
 */
export interface SearchHit {
  title: string
  details: string[]
  href: string
}

export interface SearchGroup {
  key: string
  label: string
  total: number
  items: SearchHit[]
}

export interface SearchResult {
  query: string
  groups: SearchGroup[]
}

const SEARCH_DEBOUNCE_MS = 250

export function useGlobalSearch(endpoint: string = panelUrl('/search')) {
  const query = ref('')
  const groups = ref<SearchGroup[]>([])
  const loading = ref(false)
  const activeIndex = ref(0)
  let timer: ReturnType<typeof setTimeout> | undefined
  let latest = 0

  const hits = computed<SearchHit[]>(() => groups.value.flatMap((group) => group.items))
  const active = computed<SearchHit | undefined>(() => hits.value[activeIndex.value])

  async function run(term: string): Promise<void> {
    const trimmed = term.trim()
    const ticket = ++latest

    if (trimmed === '') {
      groups.value = []
      loading.value = false
      return
    }

    loading.value = true

    try {
      const result = await apiFetch<SearchResult>(`${endpoint}?q=${encodeURIComponent(trimmed)}`)

      // A slower earlier answer must not overwrite a newer one.
      if (ticket !== latest) return

      groups.value = result.groups ?? []
      activeIndex.value = 0
    } catch {
      if (ticket === latest) groups.value = []
    } finally {
      if (ticket === latest) loading.value = false
    }
  }

  /** Type into it: waits for a pause before asking the server. */
  function update(term: string): void {
    query.value = term
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => void run(term), SEARCH_DEBOUNCE_MS)
  }

  function move(delta: number): void {
    const count = hits.value.length
    if (count === 0) return
    activeIndex.value = (activeIndex.value + delta + count) % count
  }

  /** Index of a hit across groups, for highlighting in the list. */
  function indexOf(hit: SearchHit): number {
    return hits.value.indexOf(hit)
  }

  function open(hit: SearchHit | undefined = active.value): void {
    if (!hit) return
    router.visit(hit.href)
  }

  function reset(): void {
    if (timer) clearTimeout(timer)
    latest++
    query.value = ''
    groups.value = []
    loading.value = false
    activeIndex.value = 0
  }

  return { query, groups, hits, loading, activeIndex, active, update, run, move, indexOf, open, reset }
}
