import { ref, onScopeDispose, type Ref } from 'vue'

export type SaveStatus = null | 'saving' | 'saved' | 'error'

export interface UseFieldSaver<A extends unknown[]> {
  saveStatus: Ref<SaveStatus>
  save: (...args: A) => Promise<void>
  debouncedSave: (...args: A) => void
}

/**
 * Save a form field with debouncing and transient status tracking.
 *
 * The debounce timer and the "reset status to idle" timer are kept separate so
 * they can't cancel each other, and both are cleared when the owning scope is
 * disposed so a trailing save/reset can't run against an unmounted component.
 */
export function useFieldSaver<A extends unknown[] = unknown[]>(
  saveFunction: (...args: A) => Promise<unknown>,
  debounceMs = 500,
): UseFieldSaver<A> {
  const saveStatus = ref<SaveStatus>(null)
  let debounceTimer: ReturnType<typeof setTimeout> | null = null
  let statusTimer: ReturnType<typeof setTimeout> | null = null
  // Dispatch order, so a slow save settling after a faster later one cannot
  // report its stale outcome — the status must describe the newest save the
  // user made, not whichever response the network returned last.
  let dispatched = 0

  const save = async (...args: A): Promise<void> => {
    if (debounceTimer !== null) {
      clearTimeout(debounceTimer)
      debounceTimer = null
    }
    if (statusTimer !== null) {
      clearTimeout(statusTimer)
      statusTimer = null
    }
    const sequence = ++dispatched
    saveStatus.value = 'saving'

    try {
      await saveFunction(...args)

      // A later save has been dispatched while this one was in flight: its
      // outcome supersedes this one, whatever this one was.
      if (sequence !== dispatched) return

      saveStatus.value = 'saved'
      // Clear the transient "saved" badge after a moment; an 'error' state is
      // left in place until the next save attempt.
      statusTimer = setTimeout(() => {
        saveStatus.value = null
      }, 2000)
    } catch (error) {
      if (sequence === dispatched) {
        saveStatus.value = 'error'
      }
      throw error
    }
  }

  const debouncedSave = (...args: A): void => {
    if (debounceTimer !== null) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      debounceTimer = null
      void save(...args)
    }, debounceMs)
  }

  onScopeDispose(() => {
    if (debounceTimer !== null) clearTimeout(debounceTimer)
    if (statusTimer !== null) clearTimeout(statusTimer)
  })

  return {
    saveStatus,
    save,
    debouncedSave,
  }
}
