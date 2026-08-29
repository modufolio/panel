<template>
  <Teleport :to="teleportTarget">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="ui-dialog-overlay fixed inset-0 z-50 bg-gray-950/50 backdrop-blur-sm"
        @click="handleOverlayClick"
      />
    </Transition>

    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        class="ui-dialog-container fixed inset-0 z-50 overflow-y-auto"
      >
        <div class="flex min-h-full items-start justify-center pt-16 px-4 pb-4">
          <div
            ref="dialogRef"
            :class="dialogClasses"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="title ? titleId : undefined"
            :aria-describedby="description ? descriptionId : undefined"
            class="ui-dialog relative bg-white rounded-xl shadow-xl ring-1 ring-gray-950/5"
          >
            <!-- Close Button -->
            <button
              v-if="closable"
              type="button"
              @click="close"
              aria-label="Close dialog"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-500 transition-colors"
            >
              <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <!-- Header -->
            <div v-if="$slots.header || title" class="ui-dialog-header px-6 py-4 border-b border-gray-200">
              <slot name="header">
                <h3 :id="titleId" class="text-lg font-semibold text-gray-900 pr-8">{{ title }}</h3>
                <p v-if="description" :id="descriptionId" class="mt-1 text-sm text-gray-600">{{ description }}</p>
              </slot>
            </div>

            <!-- Body -->
            <div class="ui-dialog-body px-6 py-6">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="$slots.footer" class="ui-dialog-footer px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
              <slot name="footer" />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, watch, ref } from 'vue'
import { useFocusTrap } from '../../Composables/useFocusTrap'
import { useBodyScrollLock } from '../../Primitives/useBodyScrollLock'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { getTeleportTarget } from '../../Primitives/teleportTarget'
import { useId } from '../../Primitives/useId'

// Stable across a server render and its hydration, which `Math.random()` is not
const titleId = useId(undefined, 'dialog-title')
const descriptionId = useId(undefined, 'dialog-description')

// Dialog element ref for focus trap
const dialogRef = ref<HTMLElement | null>(null)
const teleportTarget = getTeleportTarget()

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  width: {
    type: String,
    default: 'md',
    validator: (value: unknown) => ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', 'full'].includes(value as string),
  },
  closable: {
    type: Boolean,
    default: true,
  },
  closeOnOverlay: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['close', 'update:isOpen'])

const dialogClasses = computed(() => {
  const widths: Record<string, string> = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
    full: 'max-w-full mx-4',
  }

  return ['w-full', widths[props.width]]
})

function close() {
  emit('update:isOpen', false)
  emit('close')
}

function handleOverlayClick() {
  if (props.closeOnOverlay) {
    close()
  }
}

// Initialize focus trap
const { activate, deactivate } = useFocusTrap(dialogRef)

// Reference-counted, so closing a dialog opened from inside a drawer does not
// unlock the page while the drawer is still covering it.
const scrollLocked = useBodyScrollLock(props.isOpen)

// Escape goes to the topmost overlay only. Every dialog used to listen on
// `document` itself, so one press closed this dialog and its parent drawer.
// `modalElement` also hides the rest of the page from assistive technology:
// `aria-modal="true"` is unevenly supported, and without it a screen reader can
// walk out of the dialog and read a form the user cannot reach.
useDismissableLayer(() => props.isOpen, {
  elements: () => [dialogRef.value],
  onDismiss: (reason) => { if (reason === 'escape' && props.closable) close() },
  dismissOnOutsidePointer: false,
  modalElement: () => dialogRef.value,
})

// Prevent body scroll when dialog is open and manage focus trap
watch(() => props.isOpen, (isOpen) => {
  scrollLocked.value = isOpen

  if (isOpen) {
    // Synchronously, so the trap records the element that had focus before the
    // page behind is taken out of the accessibility tree — hiding it blurs
    // whatever was focused, and the record is what focus returns to on close.
    // Moving focus into the overlay is itself deferred inside the composable.
    activate()
  } else {
    deactivate()
  }
})
</script>
