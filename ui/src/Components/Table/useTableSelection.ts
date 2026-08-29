import { ref, computed, type Ref } from 'vue'
import type { TableRecord } from './tableTypes'

/**
 * Row selection for the bulk-actions bar.
 *
 * Records are held by identity rather than by id: a table's rows are the very
 * objects handed to it, so nothing has to know which field is the key.
 */
export function useTableSelection(records: () => TableRecord[]) {
  const selectedRecords: Ref<TableRecord[]> = ref([])

  const allSelected = computed(
    () => records().length > 0 && selectedRecords.value.length === records().length,
  )

  function isSelected(record: TableRecord): boolean {
    return selectedRecords.value.includes(record)
  }

  function toggleSelect(record: TableRecord): void {
    const index = selectedRecords.value.indexOf(record)

    if (index > -1) {
      selectedRecords.value.splice(index, 1)
    } else {
      selectedRecords.value.push(record)
    }
  }

  function toggleSelectAll(): void {
    selectedRecords.value = allSelected.value ? [] : [...records()]
  }

  return { selectedRecords, allSelected, isSelected, toggleSelect, toggleSelectAll }
}
