<template>
  <span class="ui-tree-column flex w-full min-w-0 items-center">
    <button
      v-if="hasChildren"
      type="button"
      @click.stop="onToggle && onToggle()"
      :style="{ marginLeft: depth * 16 + 'px' }"
      class="mr-1 inline-flex h-4 w-4 shrink-0 items-center justify-center text-gray-400 hover:text-gray-600"
      :aria-label="collapsed ? `Expand ${label}` : `Collapse ${label}`"
      :aria-expanded="!collapsed"
    >
      <svg
        class="h-3.5 w-3.5 transition-transform"
        :class="{ '-rotate-90': collapsed }"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="2"
        stroke="currentColor"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
      </svg>
    </button>
    <span
      v-else-if="depth > 0"
      class="mr-1 inline-block shrink-0 text-gray-400"
      :style="{ paddingLeft: depth * 16 + 'px' }"
    >↳</span>
    <span class="min-w-0 flex-1 truncate" :class="labelClass" :title="String(label)">{{ label }}</span>
  </span>
</template>

<script setup lang="ts">
defineProps({
  label: {
    type: [String, Number],
    default: '',
  },
  // Nesting level of this row within its tree (0 = top-level, no indent/marker).
  depth: {
    type: Number,
    default: 0,
  },
  labelClass: {
    type: String,
    default: 'font-medium text-gray-900',
  },
  // Whether this row has children — swaps the static "↳" marker for a
  // clickable collapse/expand chevron.
  hasChildren: {
    type: Boolean,
    default: false,
  },
  collapsed: {
    type: Boolean,
    default: false,
  },
  onToggle: {
    type: Function,
    default: null,
  },
})
</script>
