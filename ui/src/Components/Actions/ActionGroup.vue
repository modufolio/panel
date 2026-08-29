<template>
  <div class="ui-action-group relative inline-block text-left">
    <button
      ref="triggerRef"
      type="button"
      aria-haspopup="menu"
      :aria-expanded="isOpen"
      :aria-controls="isOpen ? menuId : undefined"
      @click="toggleDropdown"
      @keydown.down.prevent="openAndFocusFirst"
      class="inline-flex items-center justify-center gap-2 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2"
    >
      <span>{{ label }}</span>
      <svg
        class="w-5 h-5 transition-transform"
        :class="{ 'rotate-180': isOpen }"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="2"
        stroke="currentColor"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
      </svg>
    </button>

    <Teleport :to="teleportTarget">
      <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="transform opacity-0 scale-95"
        enter-to-class="transform opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="transform opacity-100 scale-100"
        leave-to-class="transform opacity-0 scale-95"
      >
        <div
          :id="menuId"
          v-show="isOpen"
          ref="dropdownRef"
          role="menu"
          class="z-50 w-56 overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
          :style="floatingStyles"
          @keydown="onKeydown"
        >
          <div class="py-1">
            <slot />
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useAnchoredPosition } from '../../Primitives/useAnchoredPosition'
import { useArrowNavigation } from '../../Primitives/useArrowNavigation'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { getTeleportTarget } from '../../Primitives/teleportTarget'
import { useId } from '../../Primitives/useId'

defineProps({
  label: {
    type: String,
    default: 'Actions',
  },
})

const isOpen = ref(false)
const triggerRef = ref<HTMLElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const menuId = useId(undefined, 'action-group-menu')
const teleportTarget = getTeleportTarget()

// Flipping, viewport clamping and following the trigger through scrolls used to
// be hand-rolled here against getBoundingClientRect().
const { floatingStyles } = useAnchoredPosition(triggerRef, dropdownRef, isOpen, {
  placement: 'bottom-end',
})

// Arrows, Home/End and type-to-jump — the menu keyboard model, which the menu
// did not have at all: items were reachable only by Tab or by mouse.
const { focusFirst, onKeydown } = useArrowNavigation(dropdownRef)

useDismissableLayer(isOpen, {
  elements: () => [triggerRef.value, dropdownRef.value],
  onDismiss: () => { isOpen.value = false },
})

function toggleDropdown() {
  isOpen.value = !isOpen.value
}

async function openAndFocusFirst() {
  isOpen.value = true
  await nextTick()
  focusFirst()
}

watch(isOpen, (value) => {
  // Only reclaim focus if it was still inside the menu — dismissing by clicking
  // elsewhere should leave focus where the user put it. This runs before the DOM
  // updates, so the menu is still around to ask.
  if (!value && dropdownRef.value?.contains(document.activeElement)) triggerRef.value?.focus()
})
</script>
