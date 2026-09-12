<template>
  <section
    ref="container"
    class="ui-layout-row group/row relative rounded-lg border border-line-strong bg-line shadow-sm"
    :class="{ 'pr-9': !disabled, 'opacity-40': isDragged }"
    :data-row-id="row.id"
    @dragover="onDragOver"
    @dragleave="dropAt = null"
    @drop="onDrop"
  >
    <div
      v-if="dropAt"
      class="pointer-events-none absolute inset-x-0 z-10 h-0.5 rounded bg-primary"
      :class="dropAt === 'before' ? '-top-1' : '-bottom-1'"
    />

    <div class="grid grid-cols-12 gap-px rounded-lg">
      <LayoutColumn
        v-for="(column, c) in row.columns"
        :key="column.id"
        :column="column"
        :address="{ row: index, column: c }"
        :field="field"
        :disabled="disabled"
        @update-block="(i: number, content: Record<string, unknown>) => emit('update-block', c, i, content)"
        @move-block="(i: number, delta: number) => emit('move-block', c, i, delta)"
        @remove-block="(i: number) => emit('remove-block', c, i)"
        @choose="(i: number) => emit('choose-block', c, i)"
        @drop-block="(from: BlockAddress, i: number) => emit('drop-block', from, { row: index, column: c, block: i })"
      />
    </div>

    <nav v-if="!disabled" class="absolute inset-y-0 right-0 z-10 flex w-9 flex-col items-center gap-0.5 rounded-r-lg border-l border-line bg-surface-sunken py-1.5 text-ink-3" aria-label="Row options">
      <button
        type="button"
        class="cursor-grab rounded p-1 hover:bg-hover hover:text-ink"
        title="Drag to reorder"
        aria-label="Drag to reorder row"
        draggable="true"
        @dragstart="onDragStart"
        @dragend="endDrag"
      >
        <Icon name="menu" class="nav-icon h-4 w-4" />
      </button>
      <button type="button" class="rounded p-1 hover:bg-hover hover:text-ink disabled:opacity-30" title="Move row up" :disabled="index === 0" @click="emit('move', -1)">
        <Icon name="chevron-up" class="nav-icon h-4 w-4" />
      </button>
      <button type="button" class="rounded p-1 hover:bg-hover hover:text-ink disabled:opacity-30" title="Move row down" :disabled="index === count - 1" @click="emit('move', 1)">
        <Icon name="chevron-down" class="nav-icon h-4 w-4" />
      </button>
      <button type="button" class="rounded p-1 hover:bg-hover hover:text-ink" title="Change layout" :disabled="!canChange" @click="emit('change')">
        <Icon name="columns" class="nav-icon h-4 w-4" />
      </button>
      <Dropdown placement="bottom-end" class="rounded p-1 hover:bg-hover hover:text-ink" title="More">
        <Icon name="ellipsis-v" class="nav-icon h-4 w-4" />
        <template #dropdown>
          <div class="min-w-44 rounded-lg border border-line bg-surface-raised py-1 text-sm shadow-lg">
            <button type="button" role="menuitem" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-ink hover:bg-hover" @click="emit('insert', 'before')">
              <Icon name="arrow-up" class="nav-icon h-4 w-4 text-ink-3" /> Insert row before
            </button>
            <button type="button" role="menuitem" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-ink hover:bg-hover" @click="emit('insert', 'after')">
              <Icon name="arrow-down" class="nav-icon h-4 w-4 text-ink-3" /> Insert row after
            </button>
            <div class="my-1 border-t border-line" />
            <button type="button" role="menuitem" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-ink hover:bg-hover" @click="emit('duplicate')">
              <Icon name="duplicate" class="nav-icon h-4 w-4 text-ink-3" /> Duplicate row
            </button>
            <button type="button" role="menuitem" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-ink hover:bg-hover disabled:opacity-40" :disabled="!canChange" @click="emit('change')">
              <Icon name="columns" class="nav-icon h-4 w-4 text-ink-3" /> Change layout
            </button>
            <div class="my-1 border-t border-line" />
            <button type="button" role="menuitem" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-danger hover:bg-danger-surface" @click="emit('remove')">
              <Icon name="trash" class="nav-icon h-4 w-4" /> Delete row
            </button>
          </div>
        </template>
      </Dropdown>
    </nav>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, type PropType } from 'vue'
import Icon from '../Core/Icon.vue'
import Dropdown from '../Core/Dropdown.vue'
import LayoutColumn from './LayoutColumn.vue'
import type { BlockAddress, LayoutRow as Row } from './layoutModel'
import { dragging, dropHalf, endDrag, startDrag } from './dragState'

const props = defineProps({
  row: { type: Object as PropType<Row>, required: true },
  index: { type: Number, required: true },
  count: { type: Number, required: true },
  field: { type: String, required: true },
  /** Whether more than one preset exists — with one, "change layout" is moot. */
  canChange: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits<{
  (e: 'update-block', column: number, index: number, content: Record<string, unknown>): void
  (e: 'move-block', column: number, index: number, delta: number): void
  (e: 'remove-block', column: number, index: number): void
  (e: 'choose-block', column: number, index: number): void
  (e: 'drop-block', from: BlockAddress, to: BlockAddress): void
  (e: 'move', delta: number): void
  (e: 'insert', where: 'before' | 'after'): void
  (e: 'change'): void
  (e: 'duplicate'): void
  (e: 'remove'): void
  /** A row was dropped on this one; `index` is where it should land. */
  (e: 'drop-row', from: number, index: number): void
}>()

const container = ref<HTMLElement | null>(null)
const dropAt = ref<'before' | 'after' | null>(null)

const isDragged = computed(() => {
  const d = dragging.value
  return d?.kind === 'row' && d.field === props.field && d.from === props.index
})

function onDragStart(event: DragEvent): void {
  startDrag(event, { kind: 'row', field: props.field, from: props.index })
}

function acceptsDrop(): boolean {
  const d = dragging.value
  return d?.kind === 'row' && d.field === props.field && !isDragged.value
}

function onDragOver(event: DragEvent): void {
  if (!acceptsDrop() || !container.value) return
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  dropAt.value = dropHalf(event, container.value)
}

function onDrop(event: DragEvent): void {
  const d = dragging.value
  const half = dropAt.value
  dropAt.value = null
  if (!acceptsDrop() || d?.kind !== 'row' || !half) return
  event.preventDefault()
  emit('drop-row', d.from, half === 'before' ? props.index : props.index + 1)
  endDrag()
}
</script>
