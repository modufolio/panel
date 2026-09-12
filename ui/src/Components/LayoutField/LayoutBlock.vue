<template>
  <div
    ref="container"
    class="ui-layout-block group/block relative rounded-md p-3 transition-colors focus-within:bg-surface-sunken/60 hover:bg-surface-sunken/60"
    :class="{ 'opacity-40': isDragged }"
    :data-block-type="block.type"
    :data-block-id="block.id"
    tabindex="-1"
    @dragover="onDragOver"
    @dragleave="dropAt = null"
    @drop="onDrop"
  >
    <div
      v-if="dropAt"
      class="pointer-events-none absolute inset-x-2 h-0.5 rounded bg-primary"
      :class="dropAt === 'before' ? '-top-px' : '-bottom-px'"
    />

    <div
      v-if="!disabled"
      class="ui-layout-block-options absolute -top-3 right-2 z-20 flex items-center gap-0.5 rounded-md border border-line bg-surface-raised px-1 py-0.5 opacity-0 shadow-sm transition-opacity focus-within:opacity-100 group-hover/block:opacity-100"
    >
      <button
        type="button"
        class="cursor-grab rounded p-1 text-ink-3 hover:bg-hover hover:text-ink"
        title="Drag to move"
        aria-label="Drag to move block"
        draggable="true"
        @dragstart="onDragStart"
        @dragend="endDrag"
      >
        <Icon name="menu" class="nav-icon h-4 w-4" />
      </button>
      <button type="button" class="rounded p-1 text-ink-3 hover:bg-hover hover:text-ink disabled:opacity-30" title="Move up" :disabled="index === 0" @click="emit('move', -1)">
        <Icon name="chevron-up" class="nav-icon h-4 w-4" />
      </button>
      <button type="button" class="rounded p-1 text-ink-3 hover:bg-hover hover:text-ink disabled:opacity-30" title="Move down" :disabled="index === count - 1" @click="emit('move', 1)">
        <Icon name="chevron-down" class="nav-icon h-4 w-4" />
      </button>
      <button type="button" class="rounded p-1 text-ink-3 hover:bg-hover hover:text-ink" title="Insert block after" @click="emit('insert-after')">
        <Icon name="plus" class="nav-icon h-4 w-4" />
      </button>
      <button type="button" class="rounded p-1 text-ink-3 hover:bg-hover hover:text-danger" title="Delete block" @click="emit('remove')">
        <Icon name="trash" class="nav-icon h-4 w-4" />
      </button>
    </div>

    <component
      :is="editor"
      v-if="editor"
      :content="block.content"
      :disabled="disabled"
      @update="patch"
    />

    <p v-else class="text-xs text-ink-3">
      <span class="font-medium text-ink-2">{{ block.type }}</span> block — no editor for this type.
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, type Component, type PropType } from 'vue'
import Icon from '../Core/Icon.vue'
import HeadingBlock from './blocks/HeadingBlock.vue'
import TextBlock from './blocks/TextBlock.vue'
import QuoteBlock from './blocks/QuoteBlock.vue'
import ImageBlock from './blocks/ImageBlock.vue'
import type { BlockAddress, LayoutBlock as Block } from './layoutModel'
import { dragging, dropHalf, endDrag, startDrag } from './dragState'

const editors: Record<string, Component> = {
  heading: HeadingBlock,
  text: TextBlock,
  quote: QuoteBlock,
  image: ImageBlock,
}

const props = defineProps({
  block: { type: Object as PropType<Block>, required: true },
  /** Where this block sits, for drag-and-drop across columns. */
  address: { type: Object as PropType<BlockAddress>, required: true },
  /** The owning field's id, so a drop only accepts blocks from the same field. */
  field: { type: String, required: true },
  index: { type: Number, required: true },
  count: { type: Number, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits<{
  (e: 'update', content: Record<string, unknown>): void
  (e: 'move', delta: number): void
  (e: 'insert-after'): void
  (e: 'remove'): void
  /** A block was dropped on this one; `index` is where it should land. */
  (e: 'drop-block', from: BlockAddress, index: number): void
}>()

const container = ref<HTMLElement | null>(null)
const dropAt = ref<'before' | 'after' | null>(null)

const editor = computed(() => editors[props.block.type])

const isDragged = computed(() => {
  const d = dragging.value
  return d?.kind === 'block'
    && d.field === props.field
    && d.from.row === props.address.row
    && d.from.column === props.address.column
    && d.from.block === props.address.block
})

function patch(changes: Record<string, unknown>): void {
  emit('update', { ...props.block.content, ...changes })
}

function onDragStart(event: DragEvent): void {
  startDrag(event, { kind: 'block', field: props.field, from: { ...props.address } })
}

function acceptsDrop(): boolean {
  const d = dragging.value
  return d?.kind === 'block' && d.field === props.field && !isDragged.value
}

function onDragOver(event: DragEvent): void {
  if (!acceptsDrop() || !container.value) return
  event.preventDefault()
  event.stopPropagation()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  dropAt.value = dropHalf(event, container.value)
}

function onDrop(event: DragEvent): void {
  const d = dragging.value
  const half = dropAt.value
  dropAt.value = null
  if (!acceptsDrop() || d?.kind !== 'block' || !half) return
  event.preventDefault()
  event.stopPropagation()
  emit('drop-block', d.from, half === 'before' ? props.index : props.index + 1)
  endDrag()
}
</script>
