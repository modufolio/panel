import type { InjectionKey, Ref } from 'vue'
import type { ResolvedTheme } from './Composables/useTheme'

/**
 * Typed provide/inject keys — the analog of Solid's typed createContext.
 *
 * Using a typed Symbol key instead of a bare string gives inject() the right
 * type automatically and makes the provider/consumer contract greppable and
 * collision-proof.
 */

/** Whether the app sidebar is collapsed. Provided by AppLayout, read by sidebars. */
export const SidebarCollapsedKey: InjectionKey<Ref<boolean>> = Symbol('sidebarCollapsed')

/**
 * The resolved theme, for the rare component that needs more than a CSS
 * variant — a canvas it paints itself, or a third-party widget with its own
 * light/dark prop. Provided by AppLayout; prefer `dark:` classes over reading
 * this, since a component that branches in JS stops working inside a subtree
 * that overrides the tokens.
 */
export const ThemeKey: InjectionKey<Ref<ResolvedTheme>> = Symbol('theme')
