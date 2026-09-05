import { computed, ref, useAttrs, type Ref } from 'vue'
import type { TableSchema } from '../Components/Table/tableSchema'
import { filterDefaults, visibleColumnDefaults } from '../Components/Table/tableSchema'
import type { BoardPayload, ResourceViewOption } from '../Components/Board/boardTypes'
import { useDrawerStack, type StackItem } from '../Components/Drawer/useDrawerStack'
import { useListFilters } from './useListFilters'

/** Self-description from ResourceListing — see its `resource` prop. */
export interface ResourceMeta {
  key: string
  baseUrl: string
  drawerType: string
  canCreate?: boolean
  canEdit?: boolean
  canDelete?: boolean
  /** Null when the resource generates no export route. */
  exportUrl?: string | null
  /**
   * The ways this resource can be looked at, and which one is showing. A
   * resource declaring no views sends a single entry, and the switcher renders
   * nothing for one option.
   */
  views?: ResourceViewOption[]
  view?: string
  /**
   * Whether board cards can be dragged. Distinct from `canEdit`, which also
   * requires the edit form route — a resource can have a board without ever
   * declaring a form.
   */
  canMove?: boolean
}

/**
 * The rows under the resource's key, as the server's JSON:API pagination
 * wraps them. `meta` is absent only when no page was rendered at all.
 */
export interface ResourceRecords {
  data: Array<Record<string, unknown>>
  meta?: {
    total: number
    per_page: number
    current_page: number
    last_page: number
    from: number
    to: number
    /** Column aggregates over the filtered set, keyed by column key. */
    summaries?: Record<string, Array<{ type: string; label: string; value: number | null }>>
  }
}

/** The props every ResourceListing-backed page receives. */
export interface ResourceListingProps {
  filters?: Record<string, unknown>
  resource: ResourceMeta
  table: TableSchema
  stack?: StackItem[]
  /** Present only while a board view is the active one. */
  board?: BoardPayload
}

/**
 * Everything a PanelResource-backed index page derives from its props.
 *
 * `ResourcePage` is its first consumer. A page that wants its own *markup* —
 * a designed header, an extra panel — calls this too and writes only what
 * differs; it does not want its own copy of "read the rows out of $attrs",
 * "seed visible columns from the schema", "wire filters to the endpoint".
 *
 *   const props = defineProps<ResourceListingProps>()
 *   const listing = useResourceListing(props)
 *
 * Nothing here is resource-specific. A page that needs more state declares it
 * beside this call rather than inside it.
 */
export function useResourceListing(props: ResourceListingProps) {
  /**
   * The rows arrive under the resource's own key (`movies`, `contacts`, …) so
   * every listing keeps a readable payload. Read through $attrs rather than
   * declaring a prop per resource.
   */
  const attrs = useAttrs()
  const records = computed<ResourceRecords>(
    () => (attrs[props.resource.key] as ResourceRecords | undefined) ?? { data: [] },
  )

  const title = computed(() => humanize(props.resource.key))

  /** 'movies' → 'Movie' — matches the server's drawerType-derived label. */
  const singularLabel = computed(() => {
    const type = props.resource.drawerType
    return type.charAt(0).toUpperCase() + type.slice(1)
  })

  /**
   * Which columns are on. Seeded from the schema, so a resource marking a
   * column `hiddenByDefault` starts with it off — held on the client because
   * hiding a column changes nothing about the query.
   */
  const visibleColumns: Ref<string[]> = ref(visibleColumnDefaults(props.table))

  /**
   * Which drawer tab is open. Held per page rather than per frame so drilling
   * to the next record keeps the section the user was reading.
   */
  const drawerTab = ref<string | null>(null)

  const drawerStack = useDrawerStack(props, props.resource.baseUrl)

  // The schema is the single source of truth for which filter keys exist.
  // The endpoint is the server's base URL as sent: it already carries the
  // resource's own prefix, which `'/' + key` through panelUrl() would not.
  const listFilters = useListFilters(props.resource.baseUrl, props.filters ?? {}, {
    defaults: filterDefaults(props.table),
    absolute: true,
  })

  function setFilter(key: string, value: unknown): void {
    ;(listFilters.form as Record<string, unknown>)[key] = value
  }

  /** Page size falls back to the current page's, so callers pass only the page. */
  function goToPage(page: number, perPage?: number): void {
    listFilters.goToPage(page, perPage ?? records.value.meta?.per_page)
  }

  return {
    records,
    title,
    singularLabel,
    visibleColumns,
    drawerTab,
    drawerStack,
    ...listFilters,
    setFilter,
    goToPage,
  }
}

/** 'form_submissions' → 'Form Submissions' */
export function humanize(key: string): string {
  return key
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}
