import { inject, onScopeDispose, watchEffect, type Ref } from 'vue'
import { DRAWER_CONTEXT_KEY, DRAWER_STACK_KEY, type DrawerContext, type DrawerStackContext } from './useIsDrawer'

/**
 * Guard the enclosing drawer against silent discards.
 *
 * Call from any component rendered inside a drawer frame, handing it the
 * form's dirty state; every close path — X, back arrow, backdrop, Escape,
 * clicking a background frame — then routes through the stack's single
 * discard dialog while the form reports dirty. Unregisters itself when the
 * component's scope ends.
 *
 *   const { isDirty } = useNestedDrawerForm(fields)
 *   useDrawerDirtyGuard(isDirty)
 *
 * Outside a drawer this is a no-op, so shared components can call it
 * unconditionally.
 */
export function useDrawerDirtyGuard(isDirty: Ref<boolean>): void {
  const stack = inject<DrawerStackContext | null>(DRAWER_STACK_KEY, null)
  const drawer = inject<DrawerContext | null>(DRAWER_CONTEXT_KEY, null)

  if (!stack || !drawer || !stack.registerDirtyCheck) {
    return
  }

  let unregister: (() => void) | null = null

  watchEffect(() => {
    // Re-register on level change (frames can shift when the stack changes
    // beneath an open form); the check itself reads the ref lazily.
    unregister?.()
    unregister = stack.registerDirtyCheck(drawer.level, () => isDirty.value)
  })

  onScopeDispose(() => {
    unregister?.()
  })
}
