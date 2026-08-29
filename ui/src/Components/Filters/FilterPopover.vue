<template>
  <div class="ui-filter-popover relative inline-block">
    <button
      ref="triggerRef"
      type="button"
      @click="togglePopover"
      class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2"
      :class="buttonClasses"
    >
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
      </svg>
      <span>Filters</span>
      <span v-if="activeFilterCount > 0" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-primary-600 rounded-full">
        {{ activeFilterCount }}
      </span>
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
          v-show="isOpen"
          ref="dropdownRef"
          class="z-50 flex flex-col origin-top-right rounded-lg bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none"
          :style="[floatingStyles, { width: `${width}px` }]"
        >
          <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
            <slot />
          </div>

          <div class="flex items-center justify-between gap-3 px-4 pb-4 pt-4 border-t border-gray-200">
            <button
              type="button"
              @click="$emit('reset')"
              class="text-sm text-gray-600 hover:text-gray-900"
            >
              Reset
            </button>
            <button
              type="button"
              @click="isOpen = false"
              class="px-3.5 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
            >
              Apply
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAnchoredPosition } from '../../Primitives/useAnchoredPosition'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { getTeleportTarget } from '../../Primitives/teleportTarget'

const props = defineProps({
  activeFilterCount: {
    type: Number,
    default: 0,
  },
  width: {
    type: Number,
    default: 320,
  },
})

defineEmits(['reset'])

const isOpen = ref(false)
const triggerRef = ref<HTMLElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const teleportTarget = getTeleportTarget()

// Flipping, viewport clamping and following the trigger through scrolls used to
// be hand-rolled here against getBoundingClientRect().
const { floatingStyles } = useAnchoredPosition(triggerRef, dropdownRef, isOpen, {
  placement: 'bottom-end',
})

useDismissableLayer(isOpen, {
  elements: () => [triggerRef.value, dropdownRef.value],
  onDismiss: () => { isOpen.value = false },
})

function togglePopover() {
  isOpen.value = !isOpen.value
}

const buttonClasses = computed(() => {
  if (props.activeFilterCount > 0) {
    return 'bg-primary-50 text-primary-700 border border-primary-200 hover:bg-primary-100'
  }
  return 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
})

</script>
