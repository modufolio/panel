<template>
  <div class="ui-table-pagination flex items-center justify-between">
    <div class="flex items-center gap-4">
      <!-- Per Page Selector -->
      <div class="flex items-center gap-2">
        <label for="per-page" class="text-sm text-gray-700">
          Show
        </label>
        <select
          id="per-page"
          :value="perPage"
          @change="$emit('update:perPage', parseInt(($event.target as HTMLSelectElement).value))"
          class="ui-input ui-input-auto px-2 py-1"
        >
          <option v-for="option in perPageOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
        <span class="text-sm text-gray-700">per page</span>
      </div>

      <!-- Records Info -->
      <div class="text-sm text-gray-700">
        Showing <span class="font-medium">{{ from }}</span> to <span class="font-medium">{{ to }}</span> of <span class="font-medium">{{ total }}</span> results
      </div>
    </div>

    <!-- Pagination Buttons -->
    <div class="flex items-center gap-1">
      <!-- First Page -->
      <button
        type="button"
        @click="$emit('goto', 1)"
        :disabled="currentPage === 1"
        class="ui-pagination-btn inline-flex items-center justify-center w-8 h-8 text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        :class="currentPage === 1 ? 'text-gray-400' : 'text-gray-700 hover:bg-gray-100'"
      >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
        </svg>
      </button>

      <!-- Previous Page -->
      <button
        type="button"
        @click="$emit('goto', currentPage - 1)"
        :disabled="currentPage === 1"
        class="ui-pagination-btn inline-flex items-center justify-center w-8 h-8 text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        :class="currentPage === 1 ? 'text-gray-400' : 'text-gray-700 hover:bg-gray-100'"
      >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
      </button>

      <!-- Page Numbers -->
      <template v-for="page in visiblePages" :key="page">
        <button
          v-if="page !== '...'"
          type="button"
          @click="$emit('goto', page)"
          class="ui-pagination-btn inline-flex items-center justify-center min-w-[2rem] h-8 px-2 text-sm font-medium rounded-lg transition-colors"
          :class="currentPage === page
            ? 'bg-primary-600 text-white'
            : 'text-gray-700 hover:bg-gray-100'
          "
        >
          {{ page }}
        </button>
        <span v-else class="inline-flex items-center justify-center w-8 h-8 text-gray-500">
          ...
        </span>
      </template>

      <!-- Next Page -->
      <button
        type="button"
        @click="$emit('goto', currentPage + 1)"
        :disabled="currentPage === lastPage"
        class="ui-pagination-btn inline-flex items-center justify-center w-8 h-8 text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        :class="currentPage === lastPage ? 'text-gray-400' : 'text-gray-700 hover:bg-gray-100'"
      >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
      </button>

      <!-- Last Page -->
      <button
        type="button"
        @click="$emit('goto', lastPage)"
        :disabled="currentPage === lastPage"
        class="ui-pagination-btn inline-flex items-center justify-center w-8 h-8 text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        :class="currentPage === lastPage ? 'text-gray-400' : 'text-gray-700 hover:bg-gray-100'"
      >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5" />
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'

const props = defineProps({
  currentPage: {
    type: Number,
    required: true,
  },
  lastPage: {
    type: Number,
    required: true,
  },
  total: {
    type: Number,
    required: true,
  },
  perPage: {
    type: Number,
    default: 15,
  },
  from: {
    type: Number,
    required: true,
  },
  to: {
    type: Number,
    required: true,
  },
  perPageOptions: {
    type: Array as PropType<number[]>,
    default: () => [10, 15, 25, 50, 100],
  },
})

defineEmits(['goto', 'update:perPage'])

const visiblePages = computed(() => {
  const pages: (number | string)[] = []
  const delta = 2 // Number of pages to show on each side of current page

  for (let i = 1; i <= props.lastPage; i++) {
    if (
      i === 1 ||
      i === props.lastPage ||
      (i >= props.currentPage - delta && i <= props.currentPage + delta)
    ) {
      pages.push(i)
    } else if (pages[pages.length - 1] !== '...') {
      pages.push('...')
    }
  }

  return pages
})
</script>
