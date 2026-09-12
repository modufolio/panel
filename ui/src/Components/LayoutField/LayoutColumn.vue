<template>
  <div
    class="ui-layout-column relative flex min-h-24 flex-col bg-surface first:rounded-l-lg"
    :class="{ 'ring-2 ring-inset ring-primary': dropHere }"
    :style="{ gridColumn: `span ${widthToSpan(column.width)}` }"
    :data-width="column.width"
    @dragover="onDragOver"
    @dragleave="dropHere = false"
    @drop="onDrop"
  >
    <div class="flex flex-1 flex-col divide-y divide-dashed divide-line">
      <LayoutBlock
        v-for="(block, i) in column.blocks"
        :key="block.id"
        :block="block"
        :address="{ ...address, block: i }"
        :field="field"
        :index="i"
        :count="column.blocks.length"
        :disabled="disabled"
        @update="(content: Record<string, unknown>) => emit('update-block', i, content)"
        @move="(delta: number) => emit('move-block', i, delta)"
        @insert-after="emit('choose', i + 1)"
        @remove="emit('remove-block', i)"
        @drop-block="(from: BlockAddress, index: number) => emit('drop-block', from, index)"
      />
    </div>

    <button
      v-if="!disabled"
      type="button"
      class="flex items-center justify-center gap-1 text-xs text-ink-3 transition-opacity hover:text-ink"
      :class="column.blocks.length === 0
        ? 'flex-1 py-6'
        : 'py-1.5 opacity-0 focus:opacity-100 group-hover/row:opacity-100'"
      @click="emit('choose', column.blocks.length)"
    >
      <Icon name="plus" class="nav-icon h-4 w-4" />
      <span v-if="column.blocks.length === 0">Add block</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, type PropType } from 'vue'
import Icon from '../Core/Icon.vue'
import LayoutBlock from './LayoutBlock.vue'
import { widthToSpan, type BlockAddress, type LayoutColumn as Column } from './layoutModel'
import { dragging, endDrag } from './dragState'

const props = defineProps({
  column: { type: Object as PropType<Column>, required: true },
  /** Row and column index; the block index is filled in per block. */
  address: { type: Object as PropType<{ row: number; column: number }>, required: true },
  field: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits<{
  (e: 'update-block', index: number, content: Record<string, unknown>): void
  (e: 'move-block', index: number, delta: number): void
  (e: 'remove-block', index: number): void
  /** Open the block selector to insert at `index`. */
  (e: 'choose', index: number): void
  (e: 'drop-block', from: BlockAddress, index: number): void
}>()

const dropHere = ref(false)

function acceptsDrop(): boolean {
  const d = dragging.value
  return d?.kind === 'block' && d.field === props.field
}

function onDragOver(event: DragEvent): void {
  if (!acceptsDrop()) return
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  dropHere.value = true
}

function onDrop(event: DragEvent): void {
  const d = dragging.value
  dropHere.value = false
  if (!acceptsDrop() || d?.kind !== 'block') return
  event.preventDefault()
  emit('drop-block', d.from, props.column.blocks.length)
  endDrag()
}
</script>
