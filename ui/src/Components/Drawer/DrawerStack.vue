<template>
  <div class="ui-drawer-stack">
    <!-- Shared overlay for the entire stack -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="hasDrawerFrames"
          class="ui-drawer-stack-overlay fixed inset-0 z-50 bg-gray-900/25"
          data-testid="drawer-overlay"
          @click="guarded(0, closeAll)"
        />
      </Transition>
    </Teleport>

    <!-- One guard dialog for every close path: X, back, backdrop, Escape. -->
    <ConfirmDialog
      :is-open="discardGuard !== null"
      title="Discard changes?"
      message="There are unsaved changes in this drawer. Closing it will discard them."
      confirm-label="Discard"
      @confirm="confirmDiscard"
      @close="discardGuard = null"
    />

    <!-- Render each stacked drawer -->
    <DrawerProvider
      v-for="(item, index) in stack"
      :key="item.key || `${item.type}-${item.data?.id || index}`"
      :item="item"
      :index="index"
    >
      <!--
        A dialog frame: one decision or one short form, from the same stack,
        the same URL and the same protocol as a drawer. Only the frame differs
        — Dialog draws its own overlay, which is why the stack's shared one
        stands down when nothing in the stack is a drawer.
      -->
      <Dialog
        v-if="isDialog(item)"
        :is-open="true"
        :title="item.title || ''"
        :description="item.description || ''"
        :width="item.width || 'md'"
        :closable="true"
        :close-on-overlay="true"
        @close="guarded(index, () => closeFrom(index))"
      >
        <template #header v-if="$slots[`header-${item.type}`]">
          <slot :name="`header-${item.type}`" :item="item" :index="index" />
        </template>

        <slot :name="item.type" :item="item" :index="index" :data="item.data">
          <DrawerRecordFrame :frame="item" />
        </slot>

        <template #footer v-if="$slots[`footer-${item.type}`]">
          <slot :name="`footer-${item.type}`" :item="item" :index="index" />
        </template>
      </Dialog>

      <Drawer
        v-else
        :is-open="true"
        :title="item.title || item.type"
        :description="item.description"
        :level="index"
        :width="item.width || width"
        :show-overlay="false"
        :closable="true"
        :close-on-overlay="false"
        :next-record-url="item.nextRecordUrl"
        :previous-record-url="item.previousRecordUrl"
        :stack-size="stack.length"
        @close="guarded(index, () => closeFrom(index))"
        @back="guarded(index, () => goBack(index))"
        @activate="guarded(index + 1, () => closeFrom(index + 1))"
      >
        <template #header v-if="$slots[`header-${item.type}`]">
          <slot :name="`header-${item.type}`" :item="item" :index="index" />
        </template>

        <!-- Use named slot per entity type, or fall back to default -->
        <slot :name="item.type" :item="item" :index="index" :data="item.data">
          <!--
            No slot for this frame's type. That is the normal case for a
            stacked record of another resource: a page declares slots for the
            types it knows, and a generated page knows only its own.

            The frame describes itself — its data, and the tabs, sections and
            field lists its resource declared — so it can be rendered without
            the page knowing the type. Previously this printed every key
            verbatim, which showed ids, foreign keys and JSON-encoded
            collections and made cross-resource stacking look broken even when
            the server had built the stack correctly.
          -->
          <DrawerRecordFrame :frame="item" />
        </slot>

        <template #footer v-if="$slots[`footer-${item.type}`]">
          <slot :name="`footer-${item.type}`" :item="item" :index="index" />
        </template>
      </Drawer>
    </DrawerProvider>
  </div>
</template>

<script setup lang="ts">
import DrawerRecordFrame from './DrawerRecordFrame.vue'
import { computed, provide, ref, shallowRef, watchEffect, defineComponent } from 'vue'
import { visitDrawer } from './visitDrawer'
import Drawer from './Drawer.vue'
import Dialog from '../Dialogs/Dialog.vue'
import ConfirmDialog from '../Dialogs/ConfirmDialog.vue'
import { DRAWER_STACK_KEY, DRAWER_CONTEXT_KEY, type DrawerContext, type DrawerStackContext } from './useIsDrawer'
import type { StackItem } from './useDrawerStack'

// Re-exported rather than redeclared: this file used to carry its own copy,
// and the two drifted — the frame's tabs reached the slot as an unknown
// property. One definition, in the composable that owns the stack.
export type { StackItem } from './useDrawerStack'

const props = defineProps({
  stack: {
    type: Array as () => StackItem[],
    default: () => [],
  },
  width: {
    type: String,
    default: 'md',
  },
  /**
   * Base URL to navigate to when closing the entire stack.
   * When provided, closing uses Inertia navigation (URL = state).
   * When not provided, emits events for manual handling.
   */
  baseUrl: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['close', 'close:all', 'back'])

/** Absent presentation means drawer — every frame built before dialogs existed. */
function isDialog(item: StackItem): boolean {
  return item.presentation === 'dialog'
}

/**
 * Dialog draws its own overlay; the stack's shared one is for drawers. Both
 * at once dims the page twice.
 */
const hasDrawerFrames = computed(() => props.stack.some((item) => !isDialog(item)))

// No warning for a frame that matches no `#type` slot. There used to be one,
// because the fallback was a raw key/value dump that looked enough like
// content for a typo to go unnoticed. The fallback is now DrawerRecordFrame,
// which draws the tabs, sections and fields the server declared — so having no
// slot is the ordinary path for every generated resource, and warning about it
// meant warning about correct code.

// Use shallowRef for the stack to avoid deep reactivity on large entity data
// (learned from Tofandel/inertia-vue3-modal's performance pattern)
const stackRef = shallowRef<StackItem[]>([])
watchEffect(() => {
  stackRef.value = props.stack
})

// Provide stack context so any descendant can push/pop/close
// without prop drilling (learned from inertia-vue3-modal's provide/inject)
/**
 * Unsaved-changes checks, by drawer level. A close only proceeds when every
 * frame it would remove reports clean; otherwise the guard dialog asks once
 * for the whole gesture. Checks are functions, not flags, so the answer is
 * read at close time — a form that became clean again closes silently.
 */
const dirtyChecks = new Map<number, () => boolean>()

/** The close action awaiting confirmation, or null when no guard is up. */
const discardGuard = ref<null | (() => void)>(null)

function registerDirtyCheck(level: number, isDirty: () => boolean): () => void {
  dirtyChecks.set(level, isDirty)

  return () => {
    if (dirtyChecks.get(level) === isDirty) {
      dirtyChecks.delete(level)
    }
  }
}

/** Would closing every frame at or above `fromLevel` discard edits? */
function hasDirtyFramesFrom(fromLevel: number): boolean {
  for (const [level, isDirty] of dirtyChecks) {
    if (level >= fromLevel && isDirty()) {
      return true
    }
  }

  return false
}

/** Run `action` now, or park it behind the guard dialog when edits would be lost. */
function guarded(fromLevel: number, action: () => void): void {
  if (hasDirtyFramesFrom(fromLevel)) {
    discardGuard.value = action
    return
  }

  action()
}

function confirmDiscard(): void {
  const action = discardGuard.value
  discardGuard.value = null
  action?.()
}

provide<DrawerStackContext>(DRAWER_STACK_KEY, {
  stack: stackRef,
  baseUrl: props.baseUrl,
  push,
  pop,
  closeAll,
  registerDirtyCheck,
})

/**
 * Renderless wrapper that provides per-drawer context via inject.
 * Each Drawer in the v-for gets its own DrawerContext.
 */
const DrawerProvider = defineComponent({
  props: {
    item: { type: Object as () => StackItem, required: true },
    index: { type: Number, required: true },
  },
  setup(providerProps, { slots }) {
    provide<DrawerContext>(DRAWER_CONTEXT_KEY, {
      item: providerProps.item,
      level: providerProps.index,
      close: () => closeFrom(providerProps.index),
      back: () => goBack(providerProps.index),
    })
    return () => slots.default?.()
  },
})

/**
 * Push a new entity onto the stack.
 */
function push(href: string): void {
  visitDrawer(href)
}

/**
 * Pop the top drawer.
 */
function pop() {
  if (props.stack.length <= 1) {
    closeAll()
    return
  }
  const prev = props.stack[props.stack.length - 2]
  if (prev?.href) {
    visitDrawer(prev.href)
  } else {
    closeAll()
  }
}

/**
 * Close from a specific index upward (remove this drawer and all above it).
 */
function closeFrom(index: number) {
  if (index === 0) {
    closeAll()
    return
  }

  const item = props.stack[index - 1]
  if (item?.href) {
    visitDrawer(item.href)
  } else {
    emit('close', index)
  }
}

/**
 * Go back one level from the given index.
 */
function goBack(index: number) {
  if (index <= 0) {
    closeAll()
    return
  }

  const previousItem = props.stack[index - 1]
  if (previousItem?.href) {
    visitDrawer(previousItem.href)
  } else {
    emit('back', index)
  }
}

/**
 * Close the entire stack.
 */
function closeAll() {
  if (props.baseUrl) {
    visitDrawer(props.baseUrl)
  } else {
    emit('close:all')
  }
}

</script>
