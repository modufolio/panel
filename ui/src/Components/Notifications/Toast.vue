<template>
  <Teleport to="body">
    <div
      class="ui-toast-container fixed z-[200] pointer-events-none"
      :class="positionClasses"
      aria-live="polite"
      data-testid="toast-container"
      :data-position="position"
    >
      <TransitionGroup
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="ui-toast pointer-events-auto mb-4 max-w-sm w-full"
          role="alert"
          data-testid="toast"
          :data-type="toast.type"
        >
          <div
            :class="toastClasses(toast)"
            class="flex items-start gap-3 p-4 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5"
          >
            <!-- Icon -->
            <div class="flex-shrink-0">
              <component :is="getIcon(toast.type)" :class="iconClasses(toast)" class="w-6 h-6" />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0 pt-0.5">
              <p v-if="toast.title" class="text-sm font-medium truncate" :class="titleClasses(toast)" data-testid="toast-title">
                {{ toast.title }}
              </p>
              <p v-if="toast.message" class="text-sm" :class="[messageClasses(toast), { 'mt-1': toast.title }]" data-testid="toast-message">
                {{ toast.message }}
              </p>

              <!-- Progress Bar -->
              <div v-if="toast.progress !== undefined" class="mt-2">
                <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                  <div
                    class="h-2.5 rounded-full transition-all duration-300 ease-out"
                    :class="progressBarClasses(toast)"
                    :style="{ width: toast.progress + '%' }"
                  ></div>
                </div>
              </div>

              <!-- Actions -->
              <div v-if="toast.actions && toast.actions.length" class="mt-3 flex gap-3">
                <button
                  v-for="(action, index) in toast.actions"
                  :key="index"
                  @click="action.handler(); removeToast(toast.id)"
                  class="text-sm font-medium hover:underline"
                  :class="actionClasses(toast)"
                >
                  {{ action.label }}
                </button>
              </div>
            </div>

            <!-- Close Button -->
            <button
              v-if="toast.closable"
              @click="removeToast(toast.id)"
              class="flex-shrink-0 inline-flex text-gray-400 hover:text-gray-500 transition-colors"
              aria-label="Close"
              data-testid="toast-close"
            >
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useToastStore, type Toast } from './useToast'

const props = defineProps({
  position: {
    type: String,
    default: 'top-right',
    validator: (value: unknown) => [
      'top-left',
      'top-center',
      'top-right',
      'bottom-left',
      'bottom-center',
      'bottom-right',
    ].includes(value as string),
  },
})

const toastStore = useToastStore()
// Unwrap the ref directly — toastStore.toasts is already a Ref<Toast[]>
const toasts = toastStore.toasts


const positionClasses = computed(() => {
  const positions: Record<string, string> = {
    'top-left': 'top-0 left-0 p-6',
    'top-center': 'top-0 left-1/2 -translate-x-1/2 p-6',
    'top-right': 'top-0 right-0 p-6',
    'bottom-left': 'bottom-0 left-0 p-6',
    'bottom-center': 'bottom-0 left-1/2 -translate-x-1/2 p-6',
    'bottom-right': 'bottom-0 right-0 p-6',
  }
  return positions[props.position]
})

function toastClasses(toast: Toast) {
  const types = {
    success: 'bg-success-50',
    error: 'bg-danger-50',
    warning: 'bg-warning-50',
    info: 'bg-info-50',
  }
  return types[toast.type] || 'bg-white'
}

function iconClasses(toast: Toast) {
  const types = {
    success: 'text-success-600',
    error: 'text-danger-600',
    warning: 'text-warning-600',
    info: 'text-info-600',
  }
  return types[toast.type] || 'text-gray-600'
}

function titleClasses(toast: Toast) {
  const types = {
    success: 'text-success-900',
    error: 'text-danger-900',
    warning: 'text-warning-900',
    info: 'text-info-900',
  }
  return types[toast.type] || 'text-gray-900'
}

function messageClasses(toast: Toast) {
  const types = {
    success: 'text-success-700',
    error: 'text-danger-700',
    warning: 'text-warning-700',
    info: 'text-info-700',
  }
  return types[toast.type] || 'text-gray-600'
}

function progressBarClasses(toast: Toast) {
  const types = {
    success: 'bg-success-600',
    error: 'bg-danger-600',
    warning: 'bg-warning-600',
    info: 'bg-info-600',
  }
  return types[toast.type] || 'bg-gray-600'
}

function actionClasses(toast: Toast) {
  const types = {
    success: 'text-success-700 hover:text-success-800',
    error: 'text-danger-700 hover:text-danger-800',
    warning: 'text-warning-700 hover:text-warning-800',
    info: 'text-info-700 hover:text-info-800',
  }
  return types[toast.type] || 'text-gray-700 hover:text-gray-800'
}

function getIcon(type: Toast['type']) {
  const icons = {
    success: {
      template: `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      `,
    },
    error: {
      template: `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      `,
    },
    warning: {
      template: `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
      `,
    },
    info: {
      template: `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
      `,
    },
  }
  return icons[type] || icons.info
}

function removeToast(id: number) {
  toastStore.remove(id)
}
</script>
