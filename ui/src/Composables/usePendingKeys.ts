import { computed, shallowRef, type ComputedRef, type ShallowRef } from 'vue'

/**
 * Keyed in-flight tracking: which records have a request running right now.
 *
 * One boolean per component answers "is anything saving"; a list needs "is
 * *this row* saving", or every row shows the same spinner and a double-click
 * on row A blocks row B. Keying the pending state is the difference —
 * the same reason frappe-ui's write actions expose `isLoading(name)` rather
 * than a bare flag.
 *
 *   const { isPending, run } = usePendingKeys()
 *   const toggle = (file) => run(file.id, async () => { ... })
 *   // template: <Spinner v-if="isPending(file.id)" />
 *
 * `run` also guards re-entry: a key with a request in flight refuses a
 * second one, which is what a double-click should mean.
 */
export interface UsePendingKeys<K = string | number> {
  /** True while `run` is executing for this key. */
  isPending: (key: K) => boolean
  /** True while anything at all is in flight. */
  anyPending: ComputedRef<boolean>
  /**
   * Execute `fn` tracked under `key`. Resolves with its result, or with
   * `undefined` when the key was already pending and the call was refused.
   * Rejections propagate after the key is released.
   */
  run: <T>(key: K, fn: () => Promise<T>) => Promise<T | undefined>
}

export function usePendingKeys<K = string | number>(): UsePendingKeys<K> {
  // Shallow, replaced wholesale rather than mutated: Set mutation is
  // invisible to Vue's reactivity, so every change swaps the Set itself —
  // which is also what keeps the generic key type out of deep-unwrap types.
  const pending: ShallowRef<Set<K>> = shallowRef(new Set<K>())

  const isPending = (key: K): boolean => pending.value.has(key)

  const anyPending = computed(() => pending.value.size > 0)

  const run = async <T>(key: K, fn: () => Promise<T>): Promise<T | undefined> => {
    if (pending.value.has(key)) return undefined

    pending.value = new Set(pending.value).add(key)

    try {
      return await fn()
    } finally {
      const next = new Set(pending.value)
      next.delete(key)
      pending.value = next
    }
  }

  return { isPending, anyPending, run }
}
