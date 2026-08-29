import { ref, reactive, watch, toRaw, type Ref } from 'vue'
import { reconcile } from '../Utils/reconcile'

function clone<T>(value: T): T {
  // Server data is JSON-shaped; a JSON round-trip drops any reactive proxy
  // wrapping and gives a plain, safely-mutable copy.
  return JSON.parse(JSON.stringify(value)) as T
}

/**
 * A locally-mutable reactive copy of server-provided data that stays in sync
 * without losing identity.
 *
 * Replaces the `reactive({ ...props.media })` + `watch(() => props.media, …)`
 * rebuild pattern: when `source` changes (Inertia re-sends the prop) the new
 * data is reconciled into the existing copy in place, so unchanged rows keep
 * their object identity and transient UI state survives navigation.
 *
 *   const media = useReconciled(() => props.media)
 *   media.value.rating = 5            // local edits are fine
 *   // …server re-sends props.media → reconciled in, local identity preserved
 */
export function useReconciled<T extends object>(source: () => T, key = 'id'): Ref<T> {
  const state = ref(clone(toRaw(source()))) as Ref<T>

  watch(
    source,
    (next) => {
      reconcile(state.value, clone(toRaw(next)), key)
    },
    { deep: true },
  )

  return state
}

/**
 * Like {@link useReconciled} but returns a `reactive` object instead of a ref —
 * a drop-in replacement for `reactive({ ...props.x })` that additionally stays
 * in sync with the source. Access members directly (`state.title`) with no
 * `.value`.
 */
export function useReconciledReactive<T extends object>(source: () => T, key = 'id'): T {
  const state = reactive(clone(toRaw(source()))) as T

  watch(
    source,
    (next) => {
      reconcile(state, clone(toRaw(next)), key)
    },
    { deep: true },
  )

  return state
}
