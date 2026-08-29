<template>
  <button
    ref="triggerRef"
    type="button"
    aria-haspopup="menu"
    :aria-expanded="isOpen"
    :aria-controls="isOpen ? menuId : undefined"
    @click="toggle"
    @keydown.down.prevent="open"
  >
    <slot />

    <Teleport v-if="isOpen" :to="teleportTarget">
      <div class="ui-dropdown-scrim fixed inset-0 z-9998 bg-black/20" @click="isOpen = false" />
      <div
        :id="menuId"
        ref="menuRef"
        role="menu"
        class="ui-dropdown-menu z-9999 overflow-auto"
        :style="floatingStyles"
        @click.stop="isOpen = !autoClose"
        @keydown="onMenuKeydown"
      >
        <slot name="dropdown" />
      </div>
    </Teleport>
  </button>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import type { Placement } from '@floating-ui/vue'
import { useAnchoredPosition } from '../../Primitives/useAnchoredPosition'
import { useArrowNavigation } from '../../Primitives/useArrowNavigation'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { getTeleportTarget } from '../../Primitives/teleportTarget'
import { useId } from '../../Primitives/useId'

const props = withDefaults(defineProps<{
  placement?: Placement
  /** Close the menu when an item inside it is clicked. */
  autoClose?: boolean
}>(), {
  placement: 'bottom-end',
  autoClose: true,
})

const isOpen = ref(false)
const triggerRef = ref<HTMLElement | null>(null)
const menuRef = ref<HTMLElement | null>(null)
const menuId = useId(undefined, 'dropdown-menu')
const teleportTarget = getTeleportTarget()

const { floatingStyles } = useAnchoredPosition(triggerRef, menuRef, isOpen, {
  placement: props.placement,
})

const { focusFirst, onKeydown } = useArrowNavigation(menuRef, { itemSelector: '[role="menuitem"], button, a[href]' })

useDismissableLayer(isOpen, {
  elements: () => [triggerRef.value, menuRef.value],
  onDismiss: () => { isOpen.value = false },
})

function toggle(): void {
  isOpen.value = !isOpen.value
}

function open(): void {
  isOpen.value = true
}

function onMenuKeydown(event: KeyboardEvent): void {
  onKeydown(event)
}

// Opening from the keyboard should land on the first item; a menu that opens
// with focus still on the trigger is unusable without a mouse.
watch(isOpen, async (value) => {
  if (!value) {
    // Only reclaim focus if it was still inside the menu — dismissing by
    // clicking something else should leave focus where the user put it. The
    // watcher runs before the DOM updates, so the menu is still around to ask.
    if (menuRef.value?.contains(document.activeElement)) triggerRef.value?.focus()
    return
  }

  await nextTick()
  focusFirst()
})
</script>
