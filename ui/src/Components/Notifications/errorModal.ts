import { ref, type Ref } from 'vue'
import { moduleSingleton } from '../../Utils/moduleSingleton'

/** What the modal is showing: the failure, in the words the viewer sees. */
export interface ErrorModalState {
  open: boolean
  status: number
  title: string
  message: string
}

export interface ErrorModalOptions {
  status?: number
  title?: string
  message?: string
}

/**
 * The one blocking error dialog, as module state rather than a component's.
 *
 * `httpErrors.ts` writes to it from the router's exception handlers — plain
 * modules outside the component tree — and `<ErrorModal />`, mounted once by
 * AppLayout, renders it. A module singleton for the same reason the toast
 * store is one: a bundler can instantiate this module twice, and a second
 * copy would be written to while the mounted one stays empty.
 */
const state: Ref<ErrorModalState> = moduleSingleton('errorModal', () =>
  ref<ErrorModalState>({ open: false, status: 0, title: '', message: '' }),
)

export function useErrorModal() {
  return { state, show: showErrorModal, close: closeErrorModal }
}

export function showErrorModal(options: ErrorModalOptions = {}): void {
  state.value = {
    open: true,
    status: options.status ?? 0,
    title: options.title || 'Something went wrong',
    message: options.message || 'An unexpected error occurred.',
  }
}

export function closeErrorModal(): void {
  state.value = { ...state.value, open: false }
}
