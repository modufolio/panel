<template>
  <Teleport :to="teleportTarget">
    <!-- Overlay (only for the first/bottom drawer in a stack) -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen && showOverlay"
        class="ui-drawer-overlay fixed inset-0 bg-gray-900/25"
        :style="{ zIndex: baseZIndex + (level * 2) }"
        @click="handleOverlayClick"
      />
    </Transition>

    <!-- Drawer Panel -->
    <Transition
      enter-active-class="transition ease-out duration-300 transform"
      enter-from-class="translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition ease-in duration-200 transform"
      leave-from-class="translate-x-0"
      leave-to-class="translate-x-full"
    >
      <div
        v-if="isOpen"
        ref="drawerRef"
        class="ui-drawer fixed inset-y-0 right-0 flex flex-col bg-white shadow-2xl dark:bg-gray-900"
        :class="widthClass"
        :style="drawerStyle"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="title ? titleId : undefined"
        :data-testid="`drawer-level-${level}`"
      >
        <!-- Header -->
        <div class="ui-drawer-header flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700" data-testid="drawer-header">
          <div class="flex items-center gap-3 min-w-0">
            <!-- Close button (X): on the left -->
            <button
              v-if="closable"
              type="button"
              @click="close"
              class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
              aria-label="Close drawer"
              data-testid="drawer-close"
            >
              <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>

            <div class="min-w-0">
              <slot name="header">
                <h2 :id="titleId" class="text-lg font-semibold text-gray-900 truncate dark:text-white" data-testid="drawer-title">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-gray-500 truncate dark:text-gray-400">{{ description }}</p>
              </slot>
            </div>
          </div>

          <!-- Back button (shown when level > 0), on the right -->
          <button
            v-if="level > 0"
            type="button"
            @click="$emit('back')"
            class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
            aria-label="Go back"
            data-testid="drawer-back"
          >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="ui-drawer-body flex-1 overflow-y-auto px-6 py-6" data-testid="drawer-body">
          <slot />
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" class="ui-drawer-footer border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800">
          <slot name="footer" />
        </div>

        <!-- Transparent interaction blocker for non-active (background) drawers -->
        <div
          v-if="isOpen && !isActiveDrawer"
          class="absolute inset-0 z-10 cursor-pointer"
          @click="$emit('activate')"
        />
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, watch, ref, onUnmounted } from 'vue'
import { useFocusTrap } from '../../Composables/useFocusTrap'
import { useBodyScrollLock } from '../../Primitives/useBodyScrollLock'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { getTeleportTarget } from '../../Primitives/teleportTarget'
import { useId } from '../../Primitives/useId'
import { visitDrawer } from './visitDrawer'

// Stable across a server render and its hydration, which `Math.random()` is not
const titleId = useId(undefined, 'drawer-title')
const drawerRef = ref<HTMLElement | null>(null)
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
    validator: (value: string) => ['sm', 'md', 'lg', 'xl', '2xl', 'full'].includes(value),
  },
  level: {
    type: Number,
    default: 0,
  },
  closable: {
    type: Boolean,
    default: true,
  },
  closeOnOverlay: {
    type: Boolean,
    default: true,
  },
  showOverlay: {
    type: Boolean,
    default: true,
  },
  nextRecordUrl: {
    type: String,
    default: '',
  },
  previousRecordUrl: {
    type: String,
    default: '',
  },
  stackSize: {
    type: Number,
    default: 1,
  },
})

const emit = defineEmits(['close', 'back', 'update:isOpen', 'activate'])

/** Module-level on purpose: the frame that started a visit is replaced by the one it loads. */
let recordNavigationInFlight = false

const isActiveDrawer = computed(() => props.level === props.stackSize - 1)

const baseZIndex = 50

const widthClass = computed(() => {
  const widths: Record<string, string> = {
    sm: 'w-full max-w-sm',
    md: 'w-full max-w-xl',
    lg: 'w-full max-w-2xl',
    xl: 'w-full max-w-3xl',
    '2xl': 'w-full max-w-4xl',
    full: 'w-full',
  }
  return widths[props.width]
})

const drawerStyle = computed(() => {
  // Depth from the top of the stack: 0 = active drawer, 1 = the one beneath…
  const depth = props.stackSize - 1 - props.level
  // Reverse offset: oldest drawer shifts left, newest stays at right edge.
  // The slight scale-down per level (Keystone's drawer stack used the same
  // trick) is what reads as a *pile* rather than overlapping panels — the
  // peeking drawer is visibly "behind", not merely beside.
  const offset = depth * 150
  return {
    zIndex: baseZIndex + (props.level * 2) + 1,
    transform: depth > 0 ? `translateX(-${offset}px) scale(${1 - depth * 0.04})` : undefined,
    transformOrigin: 'center right',
    transition: 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
  }
})

function close(): void {
  emit('update:isOpen', false)
  emit('close')
}

function handleOverlayClick(): void {
  if (props.closeOnOverlay) {
    close()
  }
}

// Focus trap
const { activate, deactivate } = useFocusTrap(drawerRef)

// Reference-counted: a stacked drawer closing must not unlock the page while
// the drawer beneath it is still covering it.
const scrollLocked = useBodyScrollLock(props.isOpen)

/**
 * Escape reaches the drawer the user can actually see. Every open Drawer used
 * to register its own `document` listener, so one press ran close() on the
 * whole stack; the layer stack delivers it to the top only.
 *
 * `modalElement` additionally hides the rest of the page from assistive
 * technology while this is the topmost overlay.
 */
useDismissableLayer(() => props.isOpen, {
  elements: () => [drawerRef.value],
  onDismiss: (reason) => { if (reason === 'escape' && props.closable) close() },
  dismissOnOutsidePointer: false,
  modalElement: () => drawerRef.value,
})

/** Record-to-record navigation, which only the active drawer should answer. */
function handleKeyDown(event: KeyboardEvent): void {
  if (!isActiveDrawer.value) {
    return
  }

  // Don't hijack arrow keys while someone is editing a field.
  const target = event.target as HTMLElement
  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
    return
  }

  if (event.key === 'ArrowUp' && props.previousRecordUrl) {
    event.preventDefault()
    navigateTo(props.previousRecordUrl)
    return
  }

  if (event.key === 'ArrowDown' && props.nextRecordUrl) {
    event.preventDefault()
    navigateTo(props.nextRecordUrl)
  }
}

/**
 * One record navigation at a time.
 *
 * A held or double-pressed arrow used to fire a second visit while the first
 * was in flight — to the same link, since the frame had not changed yet. Two
 * concurrent requests for one record is at best wasted, and when one of them
 * fails the page's state and the drawer on screen stop agreeing: the next
 * press then navigates from a frame the user is no longer looking at. The
 * flag is shared by every drawer, because the stack replaces the frame (and
 * this component) while the visit is still settling.
 */
function navigateTo(url: string): void {
  if (recordNavigationInFlight) {
    return
  }

  recordNavigationInFlight = true
  visitDrawer(url, { onFinish: () => { recordNavigationInFlight = false } })
}

watch(() => props.isOpen, (isOpen) => {
  scrollLocked.value = isOpen

  if (isOpen) {
    // Synchronously, so the trap records the element that had focus before the
    // page behind is taken out of the accessibility tree — hiding it blurs
    // whatever was focused, and the record is what focus returns to on close.
    // Moving focus into the overlay is itself deferred inside the composable.
    activate()
    document.addEventListener('keydown', handleKeyDown)
  } else {
    deactivate()
    document.removeEventListener('keydown', handleKeyDown)
  }
}, { immediate: true })

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyDown)
  deactivate()
})
</script>
