import { computed, ref, watch, type ComputedRef, type Ref } from 'vue'

/** What the user picked. `system` defers to the OS and keeps following it. */
export type ThemePreference = 'system' | 'light' | 'dark'

/** What that resolves to right now. The only two things the CSS knows about. */
export type ResolvedTheme = 'light' | 'dark'

/** Matches the `panel.<thing>` namespace savedViews and columnPreferences use. */
const STORAGE_KEY = 'panel.theme'

const PREFERENCES: readonly ThemePreference[] = ['system', 'light', 'dark']

/**
 * Light or dark, and the switch between them.
 *
 * Module-level rather than per-component: the toggle in the top bar and the
 * class on <html> have to be the same state, and every other panel-wide
 * setting (base URL, icons, media endpoints) is already a module singleton.
 *
 * Read synchronously at import, not in onMounted like
 * useLocalStoragePersistence does. A theme decided after mount is a theme the
 * user watches arrive: a switch applied from a rendered element flashes on
 * every load for exactly this reason. The class is
 * still applied here so the app is correct on its own; the consumer's blocking
 * <head> script (see the README) is what makes it correct *before first paint*.
 */

function readStored(): ThemePreference | null {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored !== null && (PREFERENCES as readonly string[]).includes(stored)) {
      return stored as ThemePreference
    }
  } catch {
    // Private windows and blocked site data throw on access, not on read.
    // A theme is not worth failing a page load over.
  }

  return null
}

const media = typeof window !== 'undefined' && typeof window.matchMedia === 'function'
  ? window.matchMedia('(prefers-color-scheme: dark)')
  : null

const stored = readStored()

const preference = ref<ThemePreference>(stored ?? 'system')

/**
 * Whether `system` is the user's answer or the absence of one. The two look
 * identical in `preference` and mean opposite things: picked deliberately it
 * follows the OS, unset it defers to the consumer's default.
 */
const chosen = ref(stored !== null)
const system = ref<ResolvedTheme>(media?.matches ? 'dark' : 'light')

media?.addEventListener('change', (event) => {
  system.value = event.matches ? 'dark' : 'light'
})

/** What the panel opens as when the user has expressed no preference. */
const fallback = ref<ResolvedTheme | 'system'>('dark')

const resolved = computed<ResolvedTheme>(() => {
  if (preference.value !== 'system') {
    return preference.value
  }

  if (chosen.value) {
    return system.value
  }

  return fallback.value === 'system' ? system.value : fallback.value
})

function apply(theme: ResolvedTheme): void {
  if (typeof document === 'undefined') return
  document.documentElement.classList.toggle('dark', theme === 'dark')
}

function set(next: ThemePreference): void {
  preference.value = next
  chosen.value = true

  try {
    localStorage.setItem(STORAGE_KEY, next)
  } catch {
    // Same as readStored: the preference just does not survive this session.
  }
}

/**
 * The panel's default when nothing is stored — `dark` unless the consumer says
 * otherwise, because the panel is built around judging images. Called by
 * createPanel(); safe to call before anything has mounted.
 */
export function setDefaultTheme(next: ResolvedTheme | 'system'): void {
  fallback.value = next
}

// One writer for the class, covering all three ways the answer can change:
// the user picking, the consumer setting a default, and the OS flipping under
// a `system` preference.
//
// immediate, because the class has to exist before the first component renders
// rather than after. sync, because the default pre-flush would leave a frame
// painted in the outgoing theme every time the switch is thrown — which is the
// flash this whole design is trying to avoid.
watch(resolved, apply, { immediate: true, flush: 'sync' })

export interface UseTheme {
  /** What the user picked, including `system`. Writable through `set`. */
  preference: Ref<ThemePreference>
  /** What that currently means. Follows the OS while preference is `system`. */
  theme: ComputedRef<ResolvedTheme>
  /** True when dark. For components that need more than a CSS variant. */
  isDark: ComputedRef<boolean>
  /** The three options, in the order a picker should show them. */
  options: readonly ThemePreference[]
  set: (next: ThemePreference) => void
}

export function useTheme(): UseTheme {
  return {
    preference,
    theme: resolved,
    isDark: computed(() => resolved.value === 'dark'),
    options: PREFERENCES,
    set,
  }
}
