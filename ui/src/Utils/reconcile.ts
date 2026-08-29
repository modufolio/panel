/**
 * Merge a fresh server payload into existing reactive state *in place*,
 * preserving object and array identity so only changed leaves update — the
 * analog of Solid's `reconcile`.
 *
 * The panel's recurring pattern is `reactive({ ...props.media })` plus a watch
 * that throws the local copy away and rebuilds it whenever Inertia re-sends the
 * prop. That resets identity on every navigation (flicker, lost transient UI
 * state, full re-render). `reconcile(local, next)` instead diffs `next` into
 * `local`: matched objects are updated in place, arrays are keyed by `id` so
 * unchanged rows keep their identity, and only genuinely new/removed entries
 * move.
 *
 * Both arguments must be plain data (objects/arrays/primitives) — call it with
 * a structured clone of the server data, not a live reference you also mutate.
 */

type PlainObject = Record<string, unknown>

function isPlainObject(value: unknown): value is PlainObject {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isMergeable(target: unknown, source: unknown): boolean {
  return (
    (isPlainObject(target) && isPlainObject(source)) ||
    (Array.isArray(target) && Array.isArray(source))
  )
}

function reconcileObject(target: PlainObject, source: PlainObject, key: string): void {
  for (const k of Object.keys(target)) {
    if (!(k in source)) delete target[k]
  }
  for (const k of Object.keys(source)) {
    const sv = source[k]
    const tv = target[k]
    if (isMergeable(tv, sv)) {
      reconcile(tv, sv, key)
    } else {
      target[k] = sv
    }
  }
}

function reconcileArray(target: unknown[], source: unknown[], key: string): void {
  const keyed = source.length > 0 && source.every((it) => isPlainObject(it) && key in it)

  if (!keyed) {
    // No stable key — reconcile positionally, keeping matched slots' identity.
    for (let i = 0; i < source.length; i++) {
      if (i < target.length && isMergeable(target[i], source[i])) {
        reconcile(target[i], source[i], key)
      } else {
        target[i] = source[i]
      }
    }
    target.length = source.length
    return
  }

  const existingByKey = new Map<unknown, PlainObject>()
  for (const item of target) {
    if (isPlainObject(item) && key in item) existingByKey.set(item[key], item)
  }

  const next = source.map((sv) => {
    const existing = existingByKey.get((sv as PlainObject)[key])
    if (existing) {
      reconcileObject(existing, sv as PlainObject, key)
      return existing
    }
    return sv
  })

  target.splice(0, target.length, ...next)
}

export function reconcile<T>(target: T, source: T, key = 'id'): T {
  if (Array.isArray(target) && Array.isArray(source)) {
    reconcileArray(target, source, key)
    return target
  }
  if (isPlainObject(target) && isPlainObject(source)) {
    reconcileObject(target, source, key)
    return target
  }
  // Primitives (or a type change) — nothing to reconcile in place.
  return source
}
