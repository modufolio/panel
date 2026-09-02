import { ref, computed, watch, onBeforeUnmount } from 'vue'
import type { TableRecord } from './tableTypes'

interface RowFocusOptions {
  /** -1 means "nothing external is driving the highlight". */
  externalIndex: () => number
  loading: () => boolean
  bulkActionsEnabled: () => boolean
  /** The rendered rows, which is what an index refers to. */
  visibleRecords: () => TableRecord[]
  toggleSelect: (record: TableRecord) => void
  onActivate: (record: TableRecord) => void
  /** Root element, so focus stays inside this table when several are mounted. */
  root: () => HTMLElement | null
}

/** How long a new index must hold before the highlight follows it. */
const FOCUS_SETTLE_MS = 150

/**
 * Keyboard navigation and the highlighted row.
 *
 * The highlight lags the index deliberately: a drawer opening or a record
 * paginating pushes several indexes through in quick succession, and moving
 * the highlight for each one reads as a flicker.
 */
export function useTableRowFocus(options: RowFocusOptions) {
  const internalFocusedRowIndex = ref(-1)
  const stableFocusedRowIndex = ref(-1)

  const focusedRowIndex = computed(() =>
    options.externalIndex() >= 0 ? options.externalIndex() : internalFocusedRowIndex.value,
  )

  let focusTimeout: ReturnType<typeof setTimeout> | null = null
  let lastStableValue = -1

  watch(
    focusedRowIndex,
    (newIndex) => {
      if (focusTimeout) clearTimeout(focusTimeout)
      if (newIndex === lastStableValue) return

      focusTimeout = setTimeout(() => {
        // Only settle if nothing moved on again while we waited.
        if (focusedRowIndex.value !== newIndex) return

        stableFocusedRowIndex.value = newIndex
        lastStableValue = newIndex
      }, FOCUS_SETTLE_MS)
    },
    { immediate: true },
  )

  onBeforeUnmount(() => {
    if (focusTimeout) clearTimeout(focusTimeout)
  })

  function focusRow(index: number): void {
    // This table's own rows, not those of a table nested in an expanded row.
    const rows = options
      .root()
      ?.querySelectorAll<HTMLElement>(':scope > .ui-table-content > table > tbody > tr.ui-table-row')

    rows?.[index]?.focus()
  }

  function navigateDown(): void {
    if (focusedRowIndex.value >= options.visibleRecords().length - 1) return

    internalFocusedRowIndex.value = focusedRowIndex.value + 1
    focusRow(focusedRowIndex.value)
  }

  function navigateUp(): void {
    if (focusedRowIndex.value <= 0) return

    internalFocusedRowIndex.value = focusedRowIndex.value - 1
    focusRow(focusedRowIndex.value)
  }

  function handleTableKeyDown(event: KeyboardEvent): void {
    if (options.loading() || options.visibleRecords().length === 0) return

    const focused = focusedRowIndex.value

    switch (event.key) {
      case 'ArrowDown':
        navigateDown()
        event.preventDefault()
        break

      case 'ArrowUp':
        navigateUp()
        event.preventDefault()
        break

      case ' ':
        if (options.bulkActionsEnabled() && focused >= 0) {
          options.toggleSelect(options.visibleRecords()[focused])
          event.preventDefault()
        }
        break

      case 'Enter':
        if (focused >= 0) {
          options.onActivate(options.visibleRecords()[focused])
          event.preventDefault()
        }
        break
    }
  }

  function handleRowFocus(index: number): void {
    internalFocusedRowIndex.value = index
  }

  return { focusedRowIndex, stableFocusedRowIndex, handleTableKeyDown, handleRowFocus }
}
