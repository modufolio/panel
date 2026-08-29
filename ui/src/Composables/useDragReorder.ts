import { ref, watch, type Ref } from 'vue'

/**
 * Composable for drag-and-drop reordering of items.
 *
 * @param itemsRef  - The reactive array of items to reorder
 * @param onReorder - Callback when reorder happens, receives the new order
 */
export function useDragReorder<T>(
  itemsRef: Ref<T[]>,
  onReorder?: (items: T[]) => void,
): {
  dragSourceIndex: Ref<number | null>
  dragOverIndex: Ref<number | null>
  handleDrop: (targetIndex: number) => void
} {
  const dragSourceIndex = ref<number | null>(null)
  const dragOverIndex = ref<number | null>(null)

  // Sync with external changes
  watch(itemsRef, () => {
    // Reset drag state if items change externally
    dragSourceIndex.value = null
    dragOverIndex.value = null
  })

  const handleDrop = (targetIndex: number): void => {
    const from = dragSourceIndex.value
    if (from === null || from === targetIndex) return

    const items = [...itemsRef.value]
    const [moved] = items.splice(from, 1)
    items.splice(targetIndex, 0, moved)
    itemsRef.value = items

    dragSourceIndex.value = null
    dragOverIndex.value = null

    if (onReorder) {
      onReorder(items)
    }
  }

  return {
    dragSourceIndex,
    dragOverIndex,
    handleDrop,
  }
}
