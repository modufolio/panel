import { ref, type Ref } from 'vue'
import { moduleSingleton } from '../../Utils/moduleSingleton'

export interface ConfirmOptions {
  title: string
  message: string
  /** Defaults to "Confirm" — say what happens instead: "Delete", "Restore". */
  confirmLabel?: string
  /**
   * Red by default, because most questions worth asking are destructive.
   * `primary` for the ones that are not: restoring, approving, switching.
   */
  tone?: 'danger' | 'primary'
}

interface ConfirmState extends ConfirmOptions {
  open: boolean
  resolve: ((confirmed: boolean) => void) | null
}

/**
 * The panel's answer to `window.confirm()`, as module state rather than a
 * component's — the same shape as {@link showErrorModal}, and mounted once by
 * AppLayout.
 *
 * Native confirm() blocks the whole tab, cannot be styled, reads as a browser
 * warning rather than as part of the panel, and on some platforms offers the
 * user a "don't show me these again" checkbox that silently turns every later
 * question into a yes. It also cannot say *why*: one line of plain text, no
 * emphasis, no consequence.
 *
 * Awaited, so a call site keeps the shape it had:
 *
 *   if (!await showConfirm({ title: 'Delete this issue?', message: '…' })) return
 *
 * For deleting a record whose server can describe the blast radius first,
 * reach for `useDeleteConfirmation` instead — it asks the server what would go
 * with the record rather than asking the user to know.
 */
const state: Ref<ConfirmState> = moduleSingleton('confirmDialog', () =>
  ref<ConfirmState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Confirm',
    tone: 'danger',
    resolve: null,
  }),
)

export function useConfirmDialog() {
  return { state, confirm: answer }
}

/** Resolves true when the viewer confirms, false on cancel, escape or backdrop. */
export function showConfirm(options: ConfirmOptions): Promise<boolean> {
  // A second question while one is open would strand the first promise
  // unresolved, and with it whatever it was guarding.
  state.value.resolve?.(false)

  return new Promise<boolean>((resolve) => {
    state.value = {
      open: true,
      title: options.title,
      message: options.message,
      confirmLabel: options.confirmLabel ?? 'Confirm',
      tone: options.tone ?? 'danger',
      resolve,
    }
  })
}

/** Settle the open question. Called by the mounted dialog, not by pages. */
function answer(confirmed: boolean): void {
  state.value.resolve?.(confirmed)
  state.value = { ...state.value, open: false, resolve: null }
}
