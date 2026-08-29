<template>
  <button
    type="button"
    class="ui-table-sort-btn text-gray-400 hover:text-gray-600"
    :aria-label="ariaLabel"
    :aria-sort="state"
    @click="$emit('sort', name)"
  >
    <svg
      v-if="state === 'none'"
      class="w-4 h-4"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      stroke-width="1.5"
      stroke="currentColor"
      aria-hidden="true"
    >
      <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
    </svg>
    <svg
      v-else-if="state === 'ascending'"
      class="w-4 h-4 text-primary-600"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      stroke-width="1.5"
      stroke="currentColor"
      aria-hidden="true"
    >
      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
    </svg>
    <svg
      v-else
      class="w-4 h-4 text-primary-600"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      stroke-width="1.5"
      stroke="currentColor"
      aria-hidden="true"
    >
      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
    </svg>
  </button>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import { columnName, type TableColumn } from './tableTypes'

const props = defineProps({
  column: {
    type: Object as PropType<TableColumn>,
    required: true,
  },
  sortColumn: {
    type: String,
    default: null,
  },
  sortDirection: {
    type: String,
    default: 'asc',
  },
})

defineEmits(['sort'])

const name = computed(() => columnName(props.column))

const state = computed(() => {
  if (props.sortColumn !== name.value) return 'none'

  return props.sortDirection === 'asc' ? 'ascending' : 'descending'
})

const ariaLabel = computed(() => {
  if (state.value === 'none') return `Sort by ${props.column.label}`

  return state.value === 'ascending'
    ? `Sorted by ${props.column.label} ascending, click to sort descending`
    : `Sorted by ${props.column.label} descending, click to remove sort`
})
</script>
