import type { TableSchema } from '../Components/Table/tableSchema'

/**
 * Which columns a viewer turned off, remembered per resource in the
 * browser and reconciled against the schema on every load: a column the
 * resource no longer has is forgotten, a new one starts as the resource
 * declares it, and a column that is not toggleable is always shown. So a
 * preference survives the resource changing, and never hides what it
 * cannot.
 */
export interface ColumnPreference {
  name: string
  hidden: boolean
}

const STORAGE_PREFIX = 'panel.columns.'

/** The visible column keys for a schema, given what was remembered (if anything). */
export function reconcileColumnPreferences(stored: ColumnPreference[] | null | undefined, schema: TableSchema | undefined): string[] {
  const remembered = new Map<string, boolean>()

  for (const entry of stored ?? []) {
    if (entry && typeof entry.name === 'string' && typeof entry.hidden === 'boolean') {
      remembered.set(entry.name, entry.hidden)
    }
  }

  return (schema?.columns ?? [])
    .filter((column) => {
      if (column.toggleable === false) return true

      const hidden = remembered.get(column.key)

      return hidden === undefined ? !column.hiddenByDefault : !hidden
    })
    .map((column) => column.key)
}

/** What to remember for a schema, from the columns currently visible: only what the viewer may toggle. */
export function columnPreferencesFor(visible: string[], schema: TableSchema | undefined): ColumnPreference[] {
  const shown = new Set(visible)

  return (schema?.columns ?? [])
    .filter((column) => column.toggleable !== false)
    .map((column) => ({ name: column.key, hidden: !shown.has(column.key) }))
}

export function loadColumnPreferences(resourceKey: string): ColumnPreference[] | null {
  try {
    const raw = localStorage.getItem(STORAGE_PREFIX + resourceKey)
    if (!raw) return null

    const parsed: unknown = JSON.parse(raw)

    return Array.isArray(parsed) ? (parsed as ColumnPreference[]) : null
  } catch {
    return null
  }
}

export function saveColumnPreferences(resourceKey: string, preferences: ColumnPreference[]): void {
  try {
    localStorage.setItem(STORAGE_PREFIX + resourceKey, JSON.stringify(preferences))
  } catch {
    // Storage unavailable (private mode, quota): the preference lives for the page only.
  }
}
