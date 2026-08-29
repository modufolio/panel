import type { InjectionKey, Ref } from 'vue'

/**
 * Typed provide/inject keys — the analog of Solid's typed createContext.
 *
 * Using a typed Symbol key instead of a bare string gives inject() the right
 * type automatically and makes the provider/consumer contract greppable and
 * collision-proof.
 */

/** Whether the app sidebar is collapsed. Provided by AppLayout, read by sidebars. */
export const SidebarCollapsedKey: InjectionKey<Ref<boolean>> = Symbol('sidebarCollapsed')
