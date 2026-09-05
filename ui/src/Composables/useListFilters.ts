import { reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { panelUrl } from '../Utils/url'
import pickBy from 'lodash/pickBy'
import throttle from 'lodash/throttle'

type FilterValues = Record<string, unknown>

export interface BaseFilters {
  search: string
  trashed: string
  sort: string
}

export interface ListFiltersOptions<T extends FilterValues = FilterValues> {
  /** Extra filter keys beyond the built-in search/trashed/sort, with their empty value. */
  defaults?: T
  /** Keys counted by activeFilterCount. Defaults to `trashed` plus every `defaults` key. */
  countable?: string[]
  /** Throttle window for the search/filter reload. */
  throttleMs?: number
  /**
   * The endpoint is already the full path — the server-sent `resource.baseUrl`,
   * which knows the resource's own prefix — and is not resolved through
   * panelUrl().
   */
  absolute?: boolean
}

export interface SortPayload {
  column: string
  direction: 'asc' | 'desc'
}

/** '' , null/undefined, [] and objects whose values are all empty (e.g. a blank date range). */
function isEmpty(value: unknown): boolean {
  if (value === null || value === undefined || value === '') return true
  if (Array.isArray(value)) return value.length === 0
  if (typeof value === 'object') return Object.values(value as object).every(isEmpty)
  return false
}

/**
 * Server-driven filter / sort / pagination state for an index page.
 *
 * `endpoint` is panel-relative ('/users') and resolved through panelUrl(),
 * unless `absolute` says it is already the full path.
 * The state lives in the URL: every change is an Inertia visit with the
 * current values as query parameters, and the server answers with the page
 * re-rendered — the client never filters rows itself.
 *
 * Only non-empty values travel in the query string. The server keeps no
 * filter or sort state between requests — an absent `sort` is the query's
 * default order — so clearing a value is expressed by leaving it out, not by
 * sending it empty.
 */
export function useListFilters<T extends FilterValues = FilterValues>(
  endpoint: string,
  filters: FilterValues = {},
  opts: ListFiltersOptions<T> = {},
) {
  const { defaults = {}, throttleMs = 150, absolute = false } = opts
  const countable = opts.countable ?? ['trashed', ...Object.keys(defaults)]

  // The empty/initial shape. Filter keys are fixed here — nothing is inferred at runtime.
  const blank: FilterValues = { search: '', trashed: '', sort: '', ...defaults }

  const form = reactive(
    Object.fromEntries(
      Object.keys(blank).map((key) => [key, filters?.[key] ?? blank[key]]),
    ),
  ) as BaseFilters & T

  const url = computed(() => (absolute ? endpoint : panelUrl(endpoint)))

  const activeFilterCount = computed(
    () => countable.filter((key) => !isEmpty((form as FilterValues)[key])).length,
  )

  const sort = computed(() => (typeof form.sort === 'string' ? form.sort : ''))

  const computedSortColumn = computed(() => {
    if (!sort.value) return null
    return sort.value.startsWith('-') ? sort.value.slice(1) : sort.value
  })

  const computedSortDirection = computed(() =>
    sort.value.startsWith('-') ? 'desc' : 'asc',
  )

  const computedParams = computed(() => {
    const params = pickBy(
      Object.fromEntries(
        Object.keys(blank).map((key) => [key, isEmpty((form as FilterValues)[key]) ? undefined : (form as FilterValues)[key]]),
      ),
    ) as Record<string, unknown>

    return params
  })

  const throttledSearch = throttle(() => {
    router.get(url.value, computedParams.value as Record<string, string>, {
      preserveState: true,
    })
  }, throttleMs)

  watch(form, throttledSearch, { deep: true })

  function updateSearch(value: string) {
    form.search = value
  }

  function handleSort({ column, direction }: SortPayload) {
    form.sort = direction === 'desc' ? `-${column}` : column
  }

  /** Restores every key to its declared empty value (types are preserved). */
  function reset() {
    Object.assign(form, blank)
  }

  /** Without a page size the server applies its default. */
  function goToPage(page: number, perPage?: number) {
    router.get(
      url.value,
      { ...computedParams.value, page: { number: page, ...(perPage === undefined ? {} : { size: perPage }) } },
      { preserveState: true },
    )
  }

  function updatePerPage(perPage: number) {
    router.get(
      url.value,
      { ...computedParams.value, page: { number: 1, size: perPage } },
      { preserveState: true },
    )
  }

  return {
    form,
    activeFilterCount,
    computedSortColumn,
    computedSortDirection,
    computedParams,
    updateSearch,
    handleSort,
    reset,
    goToPage,
    updatePerPage,
  }
}
