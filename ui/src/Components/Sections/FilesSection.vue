<template>
  <Section :heading="label" :description="help" :card="card">
    <!-- Header actions: upload button -->
    <template #headerActions>
      <slot name="headerActions">
        <button
          v-if="uploadable"
          type="button"
          class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 hover:text-primary-700 transition-colors"
          @click="$emit('upload')"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          Upload
        </button>
      </slot>
    </template>

    <!-- File grid -->
    <div v-if="files && files.length > 0" class="ui-files-grid grid gap-3" :class="gridColsClass">
      <slot name="file" v-for="file in files" :file="file">
        <!-- Default file card -->
        <div
          :key="file.id"
          class="ui-file-card group relative aspect-square rounded-lg overflow-hidden bg-gray-100 cursor-pointer ring-1 ring-gray-200 hover:ring-primary-400 transition-all"
          @click="$emit('select', file)"
        >
          <img
            v-if="file.thumbnail_url || file.url"
            :src="file.thumbnail_url || file.url"
            :alt="file.original_filename || file.title || ''"
            class="w-full h-full object-cover"
          />
          <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
        </div>
      </slot>
    </div>

    <!-- Empty state -->
    <Empty
      v-else
      :size="emptySize"
      :heading="emptyHeading || `No ${label.toLowerCase() || 'files'} yet`"
      :text="emptyText"
    >
      <slot name="empty-action" />
    </Empty>

    <!-- Footer slot (e.g. "View all" link) -->
    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </Section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Section from './Section.vue'
import Empty from '../Core/Empty.vue'

interface FileItem {
  id: string | number
  url?: string
  thumbnail_url?: string
  original_filename?: string
  title?: string
}

const props = defineProps({
  label: {
    type: String,
    default: 'Files',
  },
  help: {
    type: String,
    default: '',
  },
  card: {
    type: Boolean,
    default: true,
  },
  files: {
    type: Array as () => FileItem[],
    default: () => [],
  },
  columns: {
    type: Number,
    default: 5,
    validator: (v: number) => [2, 3, 4, 5, 6, 8].includes(v),
  },
  uploadable: {
    type: Boolean,
    default: true,
  },
  emptyHeading: {
    type: String,
    default: '',
  },
  emptyText: {
    type: String,
    default: '',
  },
  emptySize: {
    type: String,
    default: 'sm',
  },
})

defineEmits(['upload', 'select'])

const gridColsClass = computed(() => {
  const map: Record<number, string> = {
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-4',
    5: 'grid-cols-4 sm:grid-cols-5',
    6: 'grid-cols-4 sm:grid-cols-6',
    8: 'grid-cols-4 sm:grid-cols-8',
  }
  return map[props.columns] ?? 'grid-cols-5'
})
</script>
