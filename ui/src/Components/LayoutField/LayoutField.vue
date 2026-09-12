<template>
  <FieldPrimitive
    v-bind="{ width, id, label, help, error, required }"
    wrapper-class="ui-field-layout border-0 p-0 m-0"
    as="fieldset"
  >
    <div class="space-y-3" :data-field-id="id">
      <LayoutRow
        v-for="(row, r) in rows"
        :key="row.id"
        :row="row"
        :index="r"
        :count="rows.length"
        :field="id"
        :can-change="layouts.length > 1"
        :disabled="disabled"
        @update-block="(c: number, i: number, content: Record<string, unknown>) => updateBlock(r, c, i, content)"
        @move-block="(c: number, i: number, delta: number) => moveBlockBy(r, c, i, delta)"
        @remove-block="(c: number, i: number) => removeBlock(r, c, i)"
        @choose-block="(c: number, i: number) => chooseBlock(r, c, i)"
        @drop-block="dropBlock"
        @move="(delta: number) => moveRowBy(r, delta)"
        @insert="(where: 'before' | 'after') => chooseLayout(where === 'before' ? r : r + 1)"
        @change="changeLayout(r)"
        @duplicate="duplicateRow(r)"
        @remove="removeRow(r)"
        @drop-row="dropRow"
      />

      <button
        v-if="rows.length === 0 && !disabled"
        type="button"
        class="flex w-full flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-line-strong py-8 text-sm text-ink-3 transition-colors hover:border-ink-3 hover:text-ink"
        @click="chooseLayout(0)"
      >
        <Icon name="columns" class="nav-icon h-5 w-5" />
        {{ emptyMessage }}
      </button>

      <div v-else-if="!disabled" class="flex justify-center">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-line-strong bg-surface px-3 py-1.5 text-sm font-medium text-label transition-colors hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus"
          @click="chooseLayout(rows.length)"
        >
          <Icon name="plus" class="nav-icon h-4 w-4" />
          Add row
        </button>
      </div>
    </div>

    <LayoutSelector
      :show="layoutSelector.open"
      :layouts="layouts"
      :value="layoutSelector.current"
      :title="layoutSelector.change === null ? 'Select a layout' : 'Change layout'"
      @close="layoutSelector.open = false"
      @select="onLayoutSelected"
    />

    <BlockSelector
      :show="blockSelector.open"
      :types="blockTypes"
      @close="blockSelector.open = false"
      @select="onBlockSelected"
    />
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch, type PropType } from 'vue'
import FieldPrimitive from '../Fields/FieldPrimitive.vue'
import { fieldWidthProp } from '../Fields/useFieldWidth'
import { useId } from '../../Primitives/useId'
import { showConfirm } from '../Dialogs/confirm'
import Icon from '../Core/Icon.vue'
import LayoutRow from './LayoutRow.vue'
import LayoutSelector from './LayoutSelector.vue'
import BlockSelector from './BlockSelector.vue'
import {
  BLOCK_TYPES,
  changeRowLayout,
  cloneRow,
  createBlock,
  createRow,
  moveBlock,
  moveRow,
  normalizeRows,
  parseLayouts,
  rowWidths,
  toValue,
  type BlockAddress,
  type LayoutRow as Row,
} from './layoutModel'

const props = defineProps({
  ...fieldWidthProp,
  modelValue: { type: Array as PropType<unknown[]>, default: () => [] },
  id: { type: String, default: () => useId(undefined, 'layout') },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  layouts: { type: Array as PropType<string[]>, default: undefined },
  blocks: { type: Array as PropType<string[]>, default: undefined },
  emptyMessage: { type: String, default: 'No rows yet. Click to add the first one.' },
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: Row[]): void
}>()

const layouts = computed(() => parseLayouts(props.layouts))

const blockTypes = computed(() =>
  props.blocks === undefined ? BLOCK_TYPES : BLOCK_TYPES.filter((t) => props.blocks!.includes(t.type)),
)

// Local rows carry ids the stored value may lack; they are written back
// whole on every change, so from then on the value has them too.
const rows = ref<Row[]>(normalizeRows(props.modelValue))
let lastEmitted = JSON.stringify(rows.value)

watch(
  () => props.modelValue,
  (value) => {
    // Our own echo comes back through v-model; only a value from elsewhere
    // (a reload, a reset) replaces the local rows.
    if (JSON.stringify(value) === lastEmitted) return
    rows.value = normalizeRows(value)
    lastEmitted = JSON.stringify(rows.value)
  },
  { deep: true },
)

function commit(next: Row[]): void {
  rows.value = next
  const value = toValue(next)
  lastEmitted = JSON.stringify(value)
  emit('update:modelValue', value)
}

// ── Rows ─────────────────────────────────────────────────────────────────────

const layoutSelector = reactive<{ open: boolean; insertAt: number; change: number | null; current: string[] | null }>({
  open: false,
  insertAt: 0,
  change: null,
  current: null,
})

/** Insert a row at `index`; with a single preset there is nothing to choose. */
function chooseLayout(index: number): void {
  layoutSelector.change = null
  layoutSelector.current = null
  layoutSelector.insertAt = index
  if (layouts.value.length === 1) {
    insertRow(index, layouts.value[0]!)
    return
  }
  layoutSelector.open = true
}

function changeLayout(index: number): void {
  const row = rows.value[index]
  if (!row) return
  layoutSelector.change = index
  layoutSelector.current = rowWidths(row)
  layoutSelector.open = true
}

function onLayoutSelected(columns: string[]): void {
  layoutSelector.open = false
  if (layoutSelector.change === null) {
    insertRow(layoutSelector.insertAt, columns)
    return
  }
  const index = layoutSelector.change
  const row = rows.value[index]
  layoutSelector.change = null
  if (!row) return
  const next = [...rows.value]
  next.splice(index, 1, ...changeRowLayout(row, columns))
  commit(next)
}

function insertRow(index: number, columns: readonly string[]): void {
  const next = [...rows.value]
  next.splice(index, 0, createRow(columns))
  commit(next)
}

function duplicateRow(index: number): void {
  const row = rows.value[index]
  if (!row) return
  const next = [...rows.value]
  next.splice(index + 1, 0, cloneRow(row))
  commit(next)
}

async function removeRow(index: number): Promise<void> {
  const row = rows.value[index]
  if (!row) return
  const filled = row.columns.some((c) => c.blocks.length > 0)
  if (filled) {
    const ok = await showConfirm({
      title: 'Delete row',
      message: 'Delete this row and every block in it?',
      confirmLabel: 'Delete',
    })
    if (!ok) return
  }
  commit(rows.value.filter((_, i) => i !== index))
}

function moveRowBy(index: number, delta: number): void {
  const target = index + delta
  if (target < 0 || target >= rows.value.length) return
  const next = [...rows.value]
  ;[next[index], next[target]] = [next[target]!, next[index]!]
  commit(next)
}

function dropRow(from: number, index: number): void {
  commit(moveRow(rows.value, from, index))
}

// ── Blocks ───────────────────────────────────────────────────────────────────

const blockSelector = reactive<{ open: boolean; target: BlockAddress }>({
  open: false,
  target: { row: 0, column: 0, block: 0 },
})

function chooseBlock(row: number, column: number, index: number): void {
  blockSelector.target = { row, column, block: index }
  if (blockTypes.value.length === 1) {
    insertBlock(blockSelector.target, blockTypes.value[0]!.type)
    return
  }
  blockSelector.open = true
}

function onBlockSelected(type: string): void {
  blockSelector.open = false
  insertBlock(blockSelector.target, type)
}

function insertBlock(at: BlockAddress, type: string): void {
  const next = toValue(rows.value)
  const column = next[at.row]?.columns[at.column]
  if (!column) return
  column.blocks.splice(at.block, 0, createBlock(type))
  commit(next)
}

function updateBlock(row: number, column: number, index: number, content: Record<string, unknown>): void {
  const next = toValue(rows.value)
  const block = next[row]?.columns[column]?.blocks[index]
  if (!block) return
  block.content = content
  commit(next)
}

async function removeBlock(row: number, column: number, index: number): Promise<void> {
  const ok = await showConfirm({
    title: 'Delete block',
    message: 'Delete this block?',
    confirmLabel: 'Delete',
  })
  if (!ok) return
  const next = toValue(rows.value)
  const col = next[row]?.columns[column]
  if (!col) return
  col.blocks.splice(index, 1)
  commit(next)
}

function moveBlockBy(row: number, column: number, index: number, delta: number): void {
  const col = rows.value[row]?.columns[column]
  if (!col) return
  const target = index + delta
  if (target < 0 || target >= col.blocks.length) return
  const next = toValue(rows.value)
  const blocks = next[row]!.columns[column]!.blocks
  ;[blocks[index], blocks[target]] = [blocks[target]!, blocks[index]!]
  commit(next)
}

function dropBlock(from: BlockAddress, to: BlockAddress): void {
  commit(moveBlock(rows.value, from, to))
}
</script>
