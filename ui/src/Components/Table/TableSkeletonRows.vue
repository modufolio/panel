<template>
  <tr v-for="n in rows" :key="`skeleton-${n}`" class="ui-table-row animate-pulse">
    <td v-if="expandable" class="ui-table-cell px-4 py-3">
      <div class="h-4 w-4 bg-gray-200 rounded"></div>
    </td>

    <td v-if="selectable" class="ui-table-cell px-4 py-3">
      <div class="h-4 w-4 bg-gray-200 rounded"></div>
    </td>

    <td v-for="(_column, colIndex) in columns" :key="colIndex" class="ui-table-cell px-4 py-3">
      <div class="h-4 bg-gray-200 rounded" :style="{ width: skeletonWidth(colIndex) }"></div>
    </td>

    <td v-if="hasActions" class="ui-table-cell px-4 py-3">
      <div class="h-4 w-16 bg-gray-200 rounded ml-auto"></div>
    </td>
  </tr>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import type { TableColumn } from './tableTypes'

defineProps({
  rows: {
    type: Number,
    default: 5,
  },
  columns: {
    type: Array as PropType<TableColumn[]>,
    required: true,
  },
  expandable: {
    type: Boolean,
    default: false,
  },
  selectable: {
    type: Boolean,
    default: false,
  },
  hasActions: {
    type: Boolean,
    default: false,
  },
})

/** Varied widths, so the placeholder reads as text rather than as a bar chart. */
const widths = ['60%', '40%', '80%', '50%', '70%', '90%']

function skeletonWidth(index: number): string {
  return widths[index % widths.length]
}
</script>
