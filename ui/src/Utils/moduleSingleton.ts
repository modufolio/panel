/**
 * Module-level state that survives a duplicated copy of this package.
 *
 * A bundler can instantiate the same `.ts` module twice — Vite's dep
 * pre-bundler, for example, inlines a package's TS modules while leaving its
 * `.vue` SFCs as external raw-source imports, so a store module imported from
 * both sides of that boundary splits: `useToast().success()` pushes onto one
 * copy's array while `<Toast />` renders the other's, and no toast ever
 * appears. It fails silently, which is what makes it expensive to find.
 *
 * Keying the state off `globalThis` with `Symbol.for` hands every copy the
 * same object, whatever the module graph did. In a single-copy build the cost
 * is one symbol lookup at module init.
 *
 * Only for state that must be process-wide. Anything scoped to a component or
 * an app instance belongs in provide/inject instead — a global would leak
 * across SSR requests.
 *
 * (Pattern from frappe-ui's moduleSingleton, which exists for the same bug.)
 */
export function moduleSingleton<T>(key: string, create: () => T): T {
  const symbol = Symbol.for(`modufolio-panel.${key}`)
  const store = globalThis as unknown as Record<symbol, T | undefined>

  // `in` rather than a truthiness check, so a falsy value still counts as
  // initialised and `create` runs exactly once.
  if (!(symbol in store)) store[symbol] = create()

  return store[symbol] as T
}
