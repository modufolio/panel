<template>
  <Teleport :to="teleportTarget">
    <Transition>
      <div v-if="show" ref="modalRef" class="fixed inset-0 z-50" @click.self="emit('close')">
        <div class="flex min-h-screen items-center justify-center p-4">
          <div class="fixed inset-0 bg-black bg-opacity-50" @click="emit('close')" />
          <div class="relative rounded-lg bg-white shadow-xl" :class="`max-w-${maxWidth}`">
            <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
              <slot name="header" />
              <button type="button" class="ml-4 text-gray-400 hover:text-gray-500" @click="emit('close')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
            <div class="bg-white px-6 py-4">
              <slot />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useBodyScrollLock } from '../../Primitives/useBodyScrollLock'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { getTeleportTarget } from '../../Primitives/teleportTarget'

const props = withDefaults(defineProps<{
  show?: boolean
  maxWidth?: string
}>(), {
  show: false,
  maxWidth: '2xl',
})

const emit = defineEmits<{
  (e: 'close'): void
}>()

const modalRef = ref<HTMLElement | null>(null)
const teleportTarget = getTeleportTarget()

// Reference-counted: closing this modal while a drawer is still open must not
// unlock the page, which a bare `body.style.overflow = ''` did.
const scrollLocked = useBodyScrollLock(props.show)
watch(() => props.show, (value) => { scrollLocked.value = value })

// Escape reaches whichever overlay is on top of the stack, and no further —
// one press no longer closes a modal and the drawer behind it at once.
useDismissableLayer(() => props.show, {
  elements: () => [modalRef.value],
  onDismiss: (reason) => { if (reason === 'escape') emit('close') },
  dismissOnOutsidePointer: false,
})
</script>
