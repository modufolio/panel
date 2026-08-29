import { reactive, computed } from 'vue'
import type { TableRecord } from './tableTypes'

interface TreeOptions {
  /** Off for tables whose records are a flat list. */
  enabled: () => boolean
  records: () => TableRecord[]
  /** Field on each record holding its nesting level (0 = top-level). */
  depthKey: () => string
}

/**
 * Collapsible hierarchy for tables whose records arrive in depth-first order
 * (a page tree, say). Parentage is read off the depth of the *next* row, so
 * nothing needs a parent id.
 */
export function useTableTree({ enabled, records, depthKey }: TreeOptions) {
  const collapsedRecords = reactive(new Set<TableRecord>())

  /** Records with at least one immediate child directly below them. */
  const treeParents = computed(() => {
    const parents = new Set<TableRecord>()
    if (!enabled()) return parents

    const rows = records()

    for (let i = 0; i < rows.length - 1; i++) {
      const depth = rows[i][depthKey()] ?? 0
      const nextDepth = rows[i + 1][depthKey()] ?? 0

      if (nextDepth > depth) parents.add(rows[i])
    }

    return parents
  })

  const showTreeToggle = computed(() => enabled() && treeParents.value.size > 0)

  const allTreeCollapsed = computed(() => {
    if (treeParents.value.size === 0) return false

    return [...treeParents.value].every((record) => collapsedRecords.has(record))
  })

  /** Rows to actually render: a collapsed parent's whole subtree is skipped. */
  const visibleRecords = computed(() => {
    if (!enabled()) return records()

    const visible: TableRecord[] = []
    let hiddenBelowDepth: number | null = null

    for (const record of records()) {
      const depth = record[depthKey()] ?? 0

      if (hiddenBelowDepth !== null) {
        if (depth > hiddenBelowDepth) continue
        hiddenBelowDepth = null
      }

      visible.push(record)

      if (collapsedRecords.has(record)) hiddenBelowDepth = depth
    }

    return visible
  })

  function toggleCollapse(record: TableRecord): void {
    if (collapsedRecords.has(record)) {
      collapsedRecords.delete(record)
    } else {
      collapsedRecords.add(record)
    }
  }

  function toggleAllTree(): void {
    if (allTreeCollapsed.value) {
      collapsedRecords.clear()

      return
    }

    treeParents.value.forEach((record) => collapsedRecords.add(record))
  }

  return {
    collapsedRecords,
    treeParents,
    showTreeToggle,
    allTreeCollapsed,
    visibleRecords,
    toggleCollapse,
    toggleAllTree,
  }
}
