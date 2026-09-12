import { ref, computed, type Ref } from 'vue'
import { useToast } from '../Components/Notifications/useToast'
import { panelUrl } from '../Utils/url'
import { apiFetch } from '../Utils/apiFetch'

export interface NavigationItem {
  id: number
  parent_id: number | null
  position: number
  label: string
  is_visible: boolean
  type?: string
  url?: string | null
  target?: string
  [key: string]: unknown
}

export interface NavigationTreeNode extends NavigationItem {
  children: NavigationTreeNode[]
}

function buildTree(items: NavigationItem[]): NavigationTreeNode[] {
  const byParent: Record<number, NavigationItem[]> = {}
  for (const item of items) {
    const key = item.parent_id ?? 0
    if (!byParent[key]) byParent[key] = []
    byParent[key].push(item)
  }

  const build = (parentId: number): NavigationTreeNode[] => {
    return (byParent[parentId] ?? [])
      .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
      .map(item => ({ ...item, children: build(item.id) }))
  }

  return build(0)
}

/**
 * A reorderable, nestable (one level deep) navigation menu: add, remove,
 * rename, hide and drag-reposition items, with the tree derived from a flat
 * `parent_id`/`position` list rather than held as one. Domain-free — an
 * item's `type` is opaque here; a host decides what "page", "album" or
 * "link" means and builds the add-forms for them.
 *
 * `endpoint` names where the tree lives, relative to the panel's base URL:
 * `POST {endpoint}/items` to add, `DELETE {endpoint}/items/{id}` to remove,
 * `PATCH {endpoint}` with `{ items }` to save the reordered tree.
 */
export function useNavigation(initialItems: NavigationItem[], endpoint = '/api/navigation') {
  const toast = useToast()
  const items: Ref<NavigationItem[]> = ref([...initialItems].filter(item => item && item.id))
  const isDirty = ref(false)

  const tree = computed(() => buildTree(items.value))

  const addItem = async (data: Partial<NavigationItem>) => {
    try {
      const created = await apiFetch<NavigationItem>(panelUrl(`${endpoint}/items`), {
        method: 'POST',
        body: data,
      })
      items.value.push(created)
      toast.success(`Added "${created.label}"`, 'Added')
    } catch {
      toast.error('Failed to add item', 'Error')
    }
  }

  const removeItem = async (id: number) => {
    try {
      await apiFetch(panelUrl(`${endpoint}/items/${id}`), { method: 'DELETE' })
      // Remove item and promote its children to root
      items.value = items.value
        .filter(i => i.id !== id)
        .map(i => i.parent_id === id ? { ...i, parent_id: null } : i)
      isDirty.value = true
      toast.success('Removed', 'Removed')
    } catch {
      toast.error('Failed to remove item', 'Error')
    }
  }

  const saveTree = async () => {
    const saveItems = items.value.map(({ id, parent_id, position, label, is_visible }) => ({
      id,
      parent_id: parent_id ?? null,
      position,
      label,
      is_visible,
    }))

    try {
      await apiFetch(panelUrl(endpoint), {
        method: 'PATCH',
        body: { items: saveItems },
      })
      isDirty.value = false
      toast.success('Navigation saved', 'Saved')
    } catch {
      toast.error('Failed to save navigation', 'Error')
    }
  }

  const moveItem = (id: number, newParentId: number | null, newPosition: number) => {
    const movingItem = items.value.find(item => item.id === id)
    if (!movingItem) return

    // Insert among the destination's current siblings (excluding the item
    // itself) rather than overwriting its position field in place: writing
    // newPosition directly ties it with whichever sibling already held that
    // position, and a stable sort resolves a tie by original array order —
    // silently undoing the move for the single most common case, nudging an
    // item one slot within the same list.
    const destKey = newParentId ?? 0
    const destSiblings = items.value
      .filter(item => item.id !== id && (item.parent_id ?? 0) === destKey)
      .sort((a, b) => a.position - b.position)
    const clampedPosition = Math.max(0, Math.min(newPosition, destSiblings.length))
    destSiblings.splice(clampedPosition, 0, movingItem)

    const positionOf = new Map(destSiblings.map((item, i) => [item.id, i]))

    items.value = items.value.map(item => {
      if (item.id === id) {
        return { ...item, parent_id: newParentId, position: positionOf.get(id)! }
      }
      // The source parent (if different from the destination) keeps its own
      // items' relative order; a gap left by the move away is harmless —
      // rendering sorts by position, it does not require a contiguous range.
      return positionOf.has(item.id) ? { ...item, position: positionOf.get(item.id)! } : item
    })

    isDirty.value = true
  }

  const toggleVisibility = (id: number) => {
    items.value = items.value.map(item =>
      item.id === id ? { ...item, is_visible: !item.is_visible } : item
    )
    isDirty.value = true
  }

  const updateLabel = (id: number, label: string) => {
    items.value = items.value.map(item =>
      item.id === id ? { ...item, label } : item
    )
    isDirty.value = true
  }

  return {
    items,
    tree,
    isDirty,
    addItem,
    removeItem,
    saveTree,
    moveItem,
    toggleVisibility,
    updateLabel,
  }
}
