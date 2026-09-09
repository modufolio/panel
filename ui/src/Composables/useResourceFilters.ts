import type { TableSchema } from '../Components/Table/tableSchema'
import { filterDefaults } from '../Components/Table/tableSchema'
import { useListFilters } from './useListFilters'

/**
 * Filter/sort/pagination state for a PanelResource-backed index page.
 *
 * Every such page called
 * `useListFilters(endpoint, props.filters, { defaults: filterDefaults(props.table) })`
 * and then redeclared the same one-line `setFilter` beside it. This collapses
 * both into one call — the schema (`props.table`) is still the single source
 * of truth for which filter keys exist, nothing is hardcoded here.
 *
 * `perPage` exists for the same reason: each page wrapped `goToPage` purely to
 * thread its own `meta.per_page` through. Supply the getter once and the
 * returned `goToPage` is ready to bind straight to the pagination component.
 *
 * For a *generated* page, reach for `useResourceListing` instead: it wraps
 * this same wiring together with the rows, the drawer stack and the visible
 * columns, all read from the props `ResourceListing` sends. This one is for a
 * hand-written index page that owns its own markup and props.
 */
export function useResourceFilters<T extends Record<string, unknown> = Record<string, unknown>>(
  endpoint: string,
  filters: Record<string, unknown> | undefined,
  table: TableSchema,
  perPage?: () => number,
) {
  const listFilters = useListFilters<T>(endpoint, filters ?? {}, {
    defaults: filterDefaults(table) as T,
  })

  function setFilter(key: string, value: unknown) {
    (listFilters.form as Record<string, unknown>)[key] = value
  }

  /** Page size falls back to the bound getter, so callers pass only the page. */
  function goToPage(page: number, explicitPerPage?: number) {
    listFilters.goToPage(page, explicitPerPage ?? perPage?.() as number)
  }

  return { ...listFilters, setFilter, goToPage }
}
