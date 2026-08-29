import { inject, type Ref } from 'vue'
import type { StackItem } from './useDrawerStack'

/**
 * Injection keys for drawer context.
 *
 * Inspired by Tofandel/inertia-vue3-modal's provide/inject pattern:
 * any component rendered inside a drawer can detect its context
 * without prop drilling.
 */
export const DRAWER_CONTEXT_KEY = 'DrawerContext'
export const DRAWER_STACK_KEY = 'DrawerStackContext'

/**
 * HTTP header sent with drawer navigation requests.
 * Mirrors the PHP DrawerStack::HEADER constant.
 * Used by DrawerLink and useDrawerStack to signal the server
 * that this request originated from within a drawer.
 */
export const DRAWER_HEADER = 'X-Inertia-Drawer-Stack'

export interface DrawerContext {
  /** The current stack item for this drawer */
  item: StackItem
  /** Zero-based depth index of this drawer in the stack */
  level: number
  /** Close this drawer (and all above it) */
  close: () => void
  /** Go back one level */
  back: () => void
}

export interface DrawerStackContext {
  /** Full stack array (shallow — avoids deep reactivity on large data) */
  stack: Ref<StackItem[]>
  /** Base URL of the parent listing page */
  baseUrl: string
  /** Push a new entity onto the stack */
  push: (href: string) => void
  /** Pop the top drawer */
  pop: () => void
  /** Close the entire stack */
  closeAll: () => void
}

/**
 * Detect if the current component is rendered inside a drawer.
 *
 * Returns the DrawerContext for the nearest ancestor drawer,
 * or `false` if not inside a drawer.
 *
 * Usage:
 *   const drawer = useIsDrawer()
 *   if (drawer) {
 *     console.log(drawer.item.type, drawer.level)
 *   }
 */
export function useIsDrawer(): DrawerContext | false {
  return inject<DrawerContext | false>(DRAWER_CONTEXT_KEY, false)
}

/**
 * Access the drawer stack context from any descendant component.
 *
 * Returns the DrawerStackContext with push/pop/closeAll methods,
 * or `null` if not inside a DrawerStack.
 *
 * Usage:
 *   const stackCtx = useDrawerStackContext()
 *   if (stackCtx) {
 *     stackCtx.push('/contacts/12/organization/4')
 *   }
 */
export function useDrawerStackContext(): DrawerStackContext | null {
  return inject<DrawerStackContext | null>(DRAWER_STACK_KEY, null)
}
