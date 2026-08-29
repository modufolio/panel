<template>
  <div class="ui-table-header-ctn border-b border-gray-200">
    <div class="ui-table-header flex items-center justify-between gap-3 p-4">
      <div class="flex-1">
        <slot name="header" />
      </div>
      <div class="flex items-center gap-3">
        <div v-if="searchable" class="ui-table-search">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <svg class="w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
            </div>
            <input
              type="text"
              :value="search"
              placeholder="Search..."
              aria-label="Search table"
              class="ui-input block w-full pl-10 pr-3 placeholder-gray-500"
              @input="$emit('update:search', ($event.target as HTMLInputElement).value)"
            />
          </div>
        </div>

        <slot name="filters" />

        <!-- Only once there is a hierarchy to collapse -->
        <button
          v-if="showTreeToggle"
          type="button"
          class="ui-table-tree-toggle-all inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
          @click="$emit('toggleTree')"
        >
          <svg
            class="h-4 w-4 transition-transform"
            :class="{ '-rotate-90': allTreeCollapsed }"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
          </svg>
          {{ allTreeCollapsed ? 'Expand All' : 'Collapse All' }}
        </button>

        <slot name="headerActions" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps({
  searchable: {
    type: Boolean,
    default: true,
  },
  search: {
    type: String,
    default: '',
  },
  showTreeToggle: {
    type: Boolean,
    default: false,
  },
  allTreeCollapsed: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:search', 'toggleTree'])
</script>
