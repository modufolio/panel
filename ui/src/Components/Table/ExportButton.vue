<template>
  <div ref="rootRef" class="relative">
    <button
      type="button"
      aria-haspopup="menu"
      :aria-expanded="isOpen"
      :aria-controls="isOpen ? menuId : undefined"
      class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-0"
      :disabled="disabled || !canExport"
      :class="{
        'cursor-not-allowed opacity-50': disabled || !canExport,
      }"
      @click="isOpen = !isOpen"
      @keydown.down.prevent="openAndFocusFirst"
    >
      <svg
        class="h-4 w-4"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
        />
      </svg>
      <span>{{ label }}</span>
      <!--
        Always rendered, hidden with `invisible` rather than `v-if`, so the
        button keeps its width when nothing is selected and the toolbar does
        not shift. `visibility: hidden` also takes it out of the accessibility
        tree, so the placeholder count is not announced.
      -->
      <span
        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 tabular-nums"
        :class="{ invisible: exportCount === 0 }"
      >
        {{ exportCount }}
      </span>
    </button>

    <!-- Dropdown -->
    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        :id="menuId"
        v-show="isOpen"
        ref="menuRef"
        role="menu"
        class="absolute right-0 z-50 mt-2 w-48 origin-top-right rounded-lg border border-gray-200 bg-white shadow-lg"
        @keydown="onKeydown"
      >
        <div class="p-2">
          <button
            v-for="format in formats"
            :key="format.value"
            type="button"
            role="menuitem"
            class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-gray-50"
            @click="handleExport(format.value)"
          >
            <component :is="format.icon" class="h-5 w-5 text-gray-400" />
            <span class="font-medium text-gray-900">{{ format.label }}</span>
          </button>

          <div
            v-if="showPrint"
            class="my-2 border-t border-gray-200"
          />

          <button
            v-if="showPrint"
            type="button"
            role="menuitem"
            class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-gray-50"
            @click="handlePrint"
          >
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span class="font-medium text-gray-900">Print</span>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { nextTick, ref, computed, type PropType } from 'vue'
import { useTableExport, type TableColumn } from './useTableExport'
import { getCsrfToken } from '../../Utils/csrf'
import { pathIcon } from '../../Utils/pathIcon'
import { useArrowNavigation } from '../../Primitives/useArrowNavigation'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { useId } from '../../Primitives/useId'

const props = defineProps({
  data: {
    type: Array as PropType<Record<string, unknown>[]>,
    required: true,
  },
  columns: {
    type: Array as PropType<TableColumn[]>,
    required: true,
  },
  label: {
    type: String,
    default: 'Export',
  },
  filename: {
    type: String,
    default: 'export',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  showPrint: {
    type: Boolean,
    default: true,
  },
  availableFormats: {
    type: Array,
    default: () => ['csv', 'excel', 'json'],
  },
  exportUrl: {
    type: String,
    default: '',
  },
  selectedRecords: {
    type: Array as PropType<Record<string, unknown>[]>,
    default: () => [],
  },
})

const emit = defineEmits(['export', 'print'])

// Composables
const { exportView, printTable } = useTableExport()

// State
const isOpen = ref(false)
const rootRef = ref<HTMLElement | null>(null)
const menuRef = ref<HTMLElement | null>(null)
const menuId = useId(undefined, 'export-menu')

// Arrows, Home/End and type-to-jump — the menu keyboard model, which the format
// list did not have: items were reachable only by Tab or by mouse.
const { focusFirst, onKeydown } = useArrowNavigation(menuRef)

// A press outside closes it, but only while this is the topmost layer — an open
// dialog above the table takes the press instead.
useDismissableLayer(isOpen, {
  elements: () => [rootRef.value],
  onDismiss: () => { isOpen.value = false },
})

async function openAndFocusFirst() {
  isOpen.value = true
  await nextTick()
  focusFirst()
}

// Computed: number of selected records — shown in the badge only.
const exportCount = computed(() => {
  return props.selectedRecords.length
})

// The button exports the selection when there is one, otherwise the whole
// loaded set. It is only truly disabled when there is nothing at all to export.
const canExport = computed(() => {
  return props.selectedRecords.length > 0 || props.data.length > 0
})

/**
 * Records to export: the selection when present, otherwise all loaded rows.
 * Without this fallback an empty selection produced an empty file (and the
 * backend already treats an empty id list as "export everything").
 */
const recordsToExport = computed(() => {
  if (props.selectedRecords.length === 0) {
    return props.data
  }

  return props.data.filter(record => props.selectedRecords.some(
    (selected) => selected === record || (selected.id !== undefined && selected.id === record.id)
  ))
})

// Icons as render-function components; a template string would need the
// runtime compiler, which a host's runtime-only Vue build does not have.
const CSVIcon = pathIcon(
  'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  { strokeWidth: 2, class: 'h-5 w-5' },
)

const ExcelIcon = pathIcon(
  'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
  { strokeWidth: 2, class: 'h-5 w-5' },
)

const JSONIcon = pathIcon('M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', { strokeWidth: 2, class: 'h-5 w-5' })

// Available export formats
const allFormats = [
  { value: 'csv', label: 'CSV', icon: CSVIcon },
  { value: 'excel', label: 'Excel', icon: ExcelIcon },
  { value: 'json', label: 'JSON', icon: JSONIcon },
]

const formats = computed(() => {
  return allFormats.filter(format =>
    props.availableFormats.includes(format.value)
  )
})

// Functions
function handleExport(format: string) {
  isOpen.value = false

  // Generate filename with current date
  const date = new Date().toISOString().split('T')[0]
  const filename = `${props.filename}-${date}`

  const dataToExport = recordsToExport.value

  if (props.exportUrl) {
    // Backend export: send request to server
    exportViaBackend(format, filename)
  } else {
    // Client-side export
    exportView(dataToExport, props.columns, format, filename)
  }

  emit('export', { format, filename, recordCount: dataToExport.length })
}

async function exportViaBackend(format: string, filename: string) {
  const selectedIds = props.selectedRecords.map((record) => record.id).filter(Boolean)

  const exportColumns = props.columns
    .filter((col) => col.exportable !== false)
    .map((col) => ({
      key: col.key || col.name,
      label: col.label,
    }))

  try {
    // The page's own query string rides along, so the server can export what
    // the screen is showing rather than the whole table. Without it a filtered
    // view exported everything — a file that looks right and is not, which is
    // worse than an error.
    const requestUrl = props.exportUrl + window.location.search

    const response = await fetch(requestUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/octet-stream',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': getCsrfToken() ?? '',
      },
      body: JSON.stringify({
        format,
        ids: selectedIds,
        columns: exportColumns,
      }),
    })

    if (!response.ok) {
      throw new Error(`Export failed: ${response.statusText}`)
    }

    const blob = await response.blob()
    const contentDisposition = response.headers.get('Content-Disposition')
    const serverFilename = contentDisposition
      ? contentDisposition.split('filename=')[1]?.replace(/"/g, '')
      : `${filename}.${format === 'excel' ? 'xls' : format}`

    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = serverFilename
    link.style.visibility = 'hidden'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Export failed:', error)
  }
}

function handlePrint() {
  isOpen.value = false

  const title = props.filename.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
  const dataToExport = recordsToExport.value
  printTable(dataToExport, props.columns, title)

  emit('print', { recordCount: dataToExport.length })
}

</script>
