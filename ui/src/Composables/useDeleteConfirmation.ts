import { reactive } from 'vue'

/**
 * One record's deletion, from "are you sure?" to done.
 *
 * A bare confirm() asks the user to guarantee something only the server knows.
 * Given a `previewUrl`, this asks the server first and hands the answer to the
 * dialog: what would go with the record, or what is protecting it. Without one
 * it degrades to a plain confirmation — which is what a page with no preview
 * endpoint had anyway, now at least consistent with the rest of the panel.
 *
 * Deliberately not tied to generated resources: most pages that delete things
 * are hand-written, and they are the ones still calling confirm().
 */

export interface DeletionPlan {
  blocked: boolean
  /** Human labels of the records standing in the way. */
  protected?: string[]
  /** Nested tree of what would be deleted. */
  nested?: Array<{ label: string; type?: string; children?: any[] }>
  counts?: Record<string, number>
  linkCounts?: Record<string, number>
  /** True when the record is only trashed, so nothing is at stake. */
  soft?: boolean
}

export interface DeleteConfirmationState<T = any> {
  open: boolean
  record: T | null
  plan: DeletionPlan | null
  /** The preview is in flight. */
  loading: boolean
  /** The delete itself is in flight. */
  deleting: boolean
  error: string | null
}

export interface UseDeleteConfirmationOptions<T = any> {
  /**
   * Where to ask what deleting `record` would do. Omit for a plain
   * confirmation.
   */
  previewUrl?: (record: T) => string
  /**
   * A plan stated up front, for a page whose server has no preview endpoint
   * but whose behaviour is known anyway — most usefully `{ soft: true }` when
   * the delete is reversible. Without it the dialog says "cannot be undone",
   * which for a soft delete is a lie in the frightening direction.
   *
   * Ignored when `previewUrl` is given: the server's answer wins over a guess.
   */
  plan?: DeletionPlan | ((record: T) => DeletionPlan)
  /** Performs the delete once confirmed. */
  onConfirm: (record: T) => void | Promise<void>
  /** Shown when there is no preview to describe consequences with. */
  message?: (record: T) => string
}

export function useDeleteConfirmation<T = any>(options: UseDeleteConfirmationOptions<T>) {
  const state = reactive<DeleteConfirmationState<T>>({
    open: false,
    record: null,
    plan: null,
    loading: false,
    deleting: false,
    error: null,
  })

  async function request(record: T): Promise<void> {
    state.record = record as any
    state.plan = null
    state.error = null
    state.open = true

    if (!options.previewUrl) {
      state.plan = typeof options.plan === 'function'
        ? (options.plan as (record: T) => DeletionPlan)(record)
        : (options.plan ?? null)

      return
    }

    state.loading = true

    try {
      const response = await fetch(options.previewUrl(record), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        throw new Error(`Preview failed with status ${response.status}`)
      }

      state.plan = await response.json()
    } catch (error) {
      console.error(error)
      // Without a preview there is nothing honest to promise, so refuse rather
      // than fall back to a confirmation that cannot see anything.
      state.plan = {
        blocked: true,
        protected: ['Could not check what depends on this record.'],
      }
    } finally {
      state.loading = false
    }
  }

  function close(): void {
    state.open = false
    state.record = null
    state.plan = null
    state.error = null
    state.deleting = false
  }

  async function confirm(): Promise<void> {
    if (state.record === null || state.plan?.blocked || state.deleting) {
      return
    }

    state.deleting = true

    try {
      await options.onConfirm(state.record as T)
      close()
    } catch (error) {
      console.error(error)
      state.error = 'Could not delete this record.'
      state.deleting = false
    }
  }

  return { state, request, confirm, close, message: options.message }
}
