import { computed, type ComputedRef } from 'vue'
import type { StackItem } from './useDrawerStack'

/**
 * Index of the table row the drawer stack is currently showing, for
 * `<SchemaTable :external-focused-row-index>`.
 *
 * The drawer's record pagination (arrow keys) changes the stack without a
 * click, so the highlighted row must be derived from the top stack item
 * rather than from the row that opened the drawer.
 *
 * Returns -1 when no drawer is open, the top item is a different `type`
 * (e.g. a nested drawer), or the record is not in the current page of rows.
 * Omitting `type` matches any top item by id — safe with UUID ids, where a
 * nested drawer's record can never collide with a row of the listing.
 *
 *   const focusedRow = useFocusedStackRow(props, 'submission', () => props.submissions.data)
 */
export function useFocusedStackRow(
  props: { stack?: StackItem[] },
  type: string | undefined,
  records: () => Array<{ id?: unknown }> | undefined
): ComputedRef<number> {
  return computed(() => {
    const stack = props.stack ?? []
    if (stack.length === 0) return -1
    const top = stack[stack.length - 1]
    const id = top?.data?.id
    if (!id || (type !== undefined && top.type !== type)) return -1
    return (records() ?? []).findIndex((record) => record.id === id)
  })
}
