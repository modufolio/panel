import { computed, ref, type ComputedRef, type Ref } from 'vue'
import {
  activeSavedView,
  loadSavedViews,
  removeSavedView,
  saveSavedViews,
  savedViewFilters,
  upsertSavedView,
  type SavedView,
} from '../../Composables/savedViews'

export interface UseSavedViewsOptions {
  /** Which resource's views these are; they are stored per resource. */
  resourceKey: () => string
  /** The filter form, written in place when a view is applied. */
  form: Record<string, unknown>
  /** Blanks the filter form. */
  reset: () => void
  /** What currently travels in the URL — the thing a view is a name for. */
  computedParams: ComputedRef<Record<string, unknown>>
  /** The visible column keys, which a view also remembers. */
  visibleColumns: Ref<string[]>
}

/**
 * Named filter sets for a resource, remembered in the browser.
 *
 * Applying one writes the filter form, which is what the query is made of —
 * the watch inside `useListFilters` turns that into a single visit. Saving one
 * captures what the list is showing: `computedParams` is exactly what travels
 * in the URL, so a view is only ever a URL someone gave a name.
 */
export function useSavedViews({
  resourceKey,
  form,
  reset,
  computedParams,
  visibleColumns,
}: UseSavedViewsOptions): {
  savedViews: Ref<SavedView[]>
  /** The name of the view the list currently matches, if it matches one. */
  activeView: ComputedRef<string | null>
  applyView: (view: SavedView) => void
  saveView: (name: string) => void
  deleteView: (name: string) => void
} {
  const savedViews = ref<SavedView[]>(loadSavedViews(resourceKey()))

  const activeView = computed<string | null>(
    () => activeSavedView(savedViews.value, computedParams.value),
  )

  function applyView(view: SavedView): void {
    const filters = savedViewFilters(view, Object.keys(form))

    // Blank first: a view is the whole list state, not a patch over whatever
    // was filtered before it.
    reset()
    Object.assign(form, filters)

    if (view.columns?.length) visibleColumns.value = [...view.columns]
  }

  function saveView(name: string): void {
    savedViews.value = upsertSavedView(savedViews.value, {
      name,
      filters: { ...computedParams.value },
      columns: [...visibleColumns.value],
    })

    saveSavedViews(resourceKey(), savedViews.value)
  }

  function deleteView(name: string): void {
    savedViews.value = removeSavedView(savedViews.value, name)
    saveSavedViews(resourceKey(), savedViews.value)
  }

  return { savedViews, activeView, applyView, saveView, deleteView }
}
