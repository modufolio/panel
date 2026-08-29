import { ref, type Ref } from 'vue'
import { moduleSingleton } from '../../Utils/moduleSingleton'

// Types
export interface ToastAction {
  label: string
  handler: () => void
}

export interface Toast {
  id: number
  type: 'success' | 'error' | 'warning' | 'info'
  title: string
  message: string
  duration: number
  closable: boolean
  actions: ToastAction[]
  progress?: number // 0–100, undefined means no progress bar
}

export interface ToastOptions {
  type?: 'success' | 'error' | 'warning' | 'info'
  title?: string
  message?: string
  duration?: number
  closable?: boolean
  actions?: ToastAction[]
  progress?: number
}

// Global toast state, keyed off globalThis so a bundler instantiating this
// module twice (e.g. Vite pre-bundling the package's TS while leaving the
// SFCs external) still gives useToast() and <Toast /> the same array — split
// copies fail silently, with toasts pushed onto a list nothing renders.
const state = moduleSingleton('toast-store', () => ({
  toasts: ref([]) as Ref<Toast[]>,
  nextId: 0,
}))
const toasts = state.toasts

// Standalone remove — avoids `this` context loss inside setTimeout
function remove(id: number): void {
  const index = toasts.value.findIndex(t => t.id === id)
  if (index > -1) {
    toasts.value.splice(index, 1)
  }
}

export function useToastStore() {
  return {
    toasts,
    add(toast: ToastOptions): number {
      const id = ++state.nextId
      const newToast: Toast = {
        id,
        type: toast.type || 'info',
        title: toast.title || '',
        message: toast.message || '',
        duration: toast.duration ?? 5000,
        closable: toast.closable ?? true,
        actions: toast.actions || [],
        progress: toast.progress,
      }

      toasts.value.push(newToast)

      // Auto-remove after duration
      if (newToast.duration > 0) {
        setTimeout(() => remove(id), newToast.duration)
      }

      return id
    },
    update(id: number, options: Partial<ToastOptions>): void {
      const index = toasts.value.findIndex(t => t.id === id)
      if (index > -1) {
        toasts.value[index] = { ...toasts.value[index], ...options }
      }
    },
    remove,
    clear(): void {
      toasts.value = []
    },
  }
}

// Convenience composable for components
export function useToast() {
  const store = useToastStore()

  return {
    toast(options: ToastOptions): number {
      return store.add(options)
    },
    success(message: string, title = 'Success'): number {
      return store.add({
        type: 'success',
        title,
        message,
      })
    },
    error(message: string, title = 'Error'): number {
      return store.add({
        type: 'error',
        title,
        message,
      })
    },
    warning(message: string, title = 'Warning'): number {
      return store.add({
        type: 'warning',
        title,
        message,
      })
    },
    info(message: string, title = 'Info'): number {
      return store.add({
        type: 'info',
        title,
        message,
      })
    },
    update(id: number, options: Partial<ToastOptions>): void {
      store.update(id, options)
    },
    remove(id: number): void {
      store.remove(id)
    },
    clear(): void {
      store.clear()
    },
  }
}
