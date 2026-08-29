import { computed, type ComputedRef } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useIsDrawer, type DrawerContext } from './useIsDrawer'

/**
 * Context-aware page data accessor for components that render
 * in both full-page and drawer contexts.
 *
 * Inspired by Tofandel/inertia-vue3-modal's custom usePage():
 * components call useDrawerPage() instead of usePage() and get
 * the correct data regardless of context — no `if (isDrawer)` checks.
 *
 * When rendered as a full page:
 *   - Returns the standard Inertia page props
 *   - `isDrawer` is false
 *   - `drawerContext` is null
 *
 * When rendered inside a drawer:
 *   - `props` returns the drawer item's data
 *   - `parentProps` gives access to the parent page's props
 *   - `isDrawer` is true
 *   - `drawerContext` has level, close, back
 *
 * Usage:
 *   const { props, isDrawer, parentProps } = useDrawerPage()
 */
export function useDrawerPage(): {
  /** The entity data: drawer item data when in a drawer, page props otherwise */
  props: ComputedRef<Record<string, unknown>>
  /** Whether this component is inside a drawer */
  isDrawer: ComputedRef<boolean>
  /** The Inertia page props (always the parent page, even inside a drawer) */
  parentProps: ComputedRef<Record<string, unknown>>
  /** Drawer context (level, close, back) — null when not in a drawer */
  drawerContext: DrawerContext | false
} {
  const page = usePage()
  const drawer = useIsDrawer()

  const isDrawer = computed(() => drawer !== false)

  const props = computed(() => {
    if (drawer) {
      return drawer.item.data ?? {}
    }
    return page.props ?? {}
  })

  const parentProps = computed(() => page.props ?? {})

  return {
    props,
    isDrawer,
    parentProps,
    drawerContext: drawer,
  }
}
