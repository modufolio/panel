import { computed, type ComputedRef } from 'vue'
import { router } from '@inertiajs/vue3'
import { fillId, type ResourceRecords } from '../../Composables/useResourceListing'
import { recordId, type TableRecord } from '../Table/tableTypes'
import type { TableSchema } from '../Table/tableSchema'

export type CellHandler = (record: TableRecord, column: string, value: unknown) => void

export interface UseInlineCellPatchOptions {
  table: () => TableSchema
  /** The resource's `patch` route as a template, or nothing if it has none. */
  patchTemplate: () => string | null | undefined
  /** What currently travels in the URL. */
  computedParams: ComputedRef<Record<string, unknown>>
  /** The rows as sent, for the page the list is on. */
  records: ComputedRef<ResourceRecords | undefined> | { value: ResourceRecords | undefined }
}

/**
 * Save handlers for the schema's `editable` columns.
 *
 * A hand-written page supplies these itself; a generated one has nothing to
 * supply them from, which is why an editable column used to render a control
 * that saved nowhere. The server's `patch` route is the missing half: one
 * field, keyed by column, allowlisted there against the same schema this
 * reads — so what the client offers and what the server accepts cannot drift.
 *
 * Empty when the resource generated no patch route, which leaves the column
 * read-only rather than pretending.
 */
export function useInlineCellPatch({
  table,
  patchTemplate,
  computedParams,
  records,
}: UseInlineCellPatchOptions): {
  cellHandlers: ComputedRef<Record<string, CellHandler>>
  /** The list's own state as a query string — exported for tests and reuse. */
  listQuery: ComputedRef<string>
} {
  /**
   * The list's state as the redirect will need it: Inertia reloads the URL the
   * server redirected to, so without this, editing a cell on page 3 of a
   * filtered list would answer with page 1 of an unfiltered one.
   */
  const listQuery = computed<string>(() => {
    const query = new URLSearchParams()

    for (const [name, value] of Object.entries(computedParams.value)) {
      if (value === undefined || value === null || value === '') continue

      query.set(name, String(value))
    }

    // The filter form does not hold the page — pagination travels as its own
    // param — so it is read from the rows the server sent.
    const meta = records.value?.meta

    if (meta?.current_page && meta.current_page > 1) {
      query.set('page[number]', String(meta.current_page))

      if (meta.per_page) query.set('page[size]', String(meta.per_page))
    }

    return query.toString()
  })

  const cellHandlers = computed<Record<string, CellHandler>>(() => {
    const template = patchTemplate()

    if (!template) return {}

    const handlers: Record<string, CellHandler> = {}

    for (const column of table().columns ?? []) {
      if (!column.editable) continue

      handlers[column.key] = (record, key, value) => {
        const url = fillId(template, recordId(record))

        if (!url) return

        // Inertia's payload type is FormData or a plain record of scalars; the
        // value is whatever control the column declared, narrowed at the edge.
        const payload = { [key]: value as string | number | boolean | null }
        const query = listQuery.value

        router.patch(query === '' ? url : `${url}?${query}`, payload, { preserveScroll: true })
      }
    }

    return handlers
  })

  return { cellHandlers, listQuery }
}
