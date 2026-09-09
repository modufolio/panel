/**
 * A named list state — filters, search, sort and which columns are on —
 * remembered per resource in the browser, the way column preferences already
 * are.
 *
 * "Overdue issues" or "Unpublished screenings" is a question someone asks
 * every day and rebuilds by hand every time. Everything it is made of already
 * exists: the filter form is the query, the query is the URL, and the columns
 * are a client-side preference. What was missing is a name for a combination.
 *
 * Held in `localStorage` rather than on the server for the same reason column
 * preferences are: nothing here changes what the server would answer, so it
 * needs no table, no migration and no endpoint. The trade is that a view does
 * not follow the viewer to another browser, and cannot be shared — when either
 * becomes the point, this module is the seam to move behind an endpoint, and
 * the shape below is what it would store.
 */

/** A filter set someone named. The name is its identity. */
export interface SavedView {
  name: string
  /** The list query: the non-empty filter form values, `search` and `sort` included. */
  filters: Record<string, unknown>
  /** Which columns were on. Absent for a view saved before columns were captured. */
  columns?: string[]
}

const STORAGE_PREFIX = 'panel.views.'

/** Whether a stored entry is still a view, rather than junk from an older shape. */
function isSavedView(value: unknown): value is SavedView {
  if (typeof value !== 'object' || value === null) return false

  const view = value as Partial<SavedView>

  return typeof view.name === 'string'
    && view.name !== ''
    && typeof view.filters === 'object'
    && view.filters !== null
    && (view.columns === undefined || Array.isArray(view.columns))
}

export function loadSavedViews(resourceKey: string): SavedView[] {
  try {
    const raw = localStorage.getItem(STORAGE_PREFIX + resourceKey)
    if (!raw) return []

    const parsed: unknown = JSON.parse(raw)

    return Array.isArray(parsed) ? parsed.filter(isSavedView) : []
  } catch {
    // Unreadable storage (private mode, a half-written value): no views, not a crash.
    return []
  }
}

export function saveSavedViews(resourceKey: string, views: SavedView[]): void {
  try {
    localStorage.setItem(STORAGE_PREFIX + resourceKey, JSON.stringify(views))
  } catch {
    // Storage unavailable or full: the views live for this page only.
  }
}

/**
 * Add a view, or replace the one that already has its name.
 *
 * Saving over a name is how a view is *edited* — there is no rename, because
 * a view is only ever the current list state under a label.
 */
export function upsertSavedView(views: SavedView[], view: SavedView): SavedView[] {
  const index = views.findIndex((existing) => existing.name === view.name)

  if (index === -1) return [...views, view]

  return views.map((existing, at) => (at === index ? view : existing))
}

export function removeSavedView(views: SavedView[], name: string): SavedView[] {
  return views.filter((view) => view.name !== name)
}

/**
 * The view's filters, keeping only keys the resource still declares.
 *
 * Same reconciliation column preferences do: a filter the resource dropped is
 * forgotten rather than written back into a form that has no place for it, and
 * a filter added since is left at its blank value.
 */
export function savedViewFilters(view: SavedView, keys: string[]): Record<string, unknown> {
  const allowed = new Set(keys)
  const filters: Record<string, unknown> = {}

  for (const [key, value] of Object.entries(view.filters)) {
    if (allowed.has(key)) filters[key] = value
  }

  return filters
}

/** A stable string for a filter set, so two can be compared regardless of key order. */
function fingerprint(filters: Record<string, unknown>): string {
  return JSON.stringify(
    Object.entries(filters)
      .filter(([, value]) => value !== undefined && value !== null && value !== '')
      .sort(([a], [b]) => a.localeCompare(b)),
  )
}

/** Whether the list is currently showing what this view describes. */
export function savedViewMatches(view: SavedView, params: Record<string, unknown>): boolean {
  return fingerprint(view.filters) === fingerprint(params)
}

/** The name of the view the list is currently showing, if any. */
export function activeSavedView(views: SavedView[], params: Record<string, unknown>): string | null {
  return views.find((view) => savedViewMatches(view, params))?.name ?? null
}
