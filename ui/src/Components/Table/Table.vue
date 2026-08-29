<template>
  <div ref="root" class="ui-table-wrapper bg-white rounded-lg shadow-sm ring-1 ring-gray-950/5 overflow-hidden">
    <TableToolbar
      v-if="$slots.header || searchable || $slots.headerActions || showTreeToggle"
      :searchable="searchable"
      :search="search"
      :show-tree-toggle="showTreeToggle"
      :all-tree-collapsed="allTreeCollapsed"
      @update:search="$emit('update:search', $event)"
      @toggle-tree="toggleAllTree"
    >
      <template v-if="$slots.header" #header><slot name="header" /></template>
      <template v-if="$slots.filters" #filters><slot name="filters" /></template>
      <template v-if="$slots.headerActions" #headerActions>
        <slot name="headerActions" :selectedRecords="selectedRecords" />
      </template>
    </TableToolbar>

    <!-- Active filters, between the toolbar that sets them and the rows they
         narrowed. -->
    <slot name="filterIndicators" />

    <!-- Bulk Actions Bar -->
    <div
      v-if="bulkActionsEnabled && selectedRecords.length > 0"
      class="ui-table-bulk-actions flex items-center justify-between gap-3 bg-primary-50 px-4 py-3 border-y border-primary-200"
    >
      <div class="flex items-center gap-3">
        <span class="text-sm font-medium text-primary-700">
          {{ selectedRecords.length }} selected
        </span>
      </div>
      <div class="flex items-center gap-2">
        <slot name="bulkActions" :selected="selectedRecords" />
      </div>
    </div>

    <!-- Table -->
    <div class="ui-table-content overflow-x-auto overflow-y-visible">
      <table class="ui-table w-full divide-y divide-gray-200" :class="treeCollapsible ? 'table-fixed' : 'table-auto'" @keydown="handleTableKeyDown">
        <colgroup v-if="treeCollapsible">
          <col v-if="expandable" class="w-4" />
          <col v-if="bulkActionsEnabled" class="w-4" />
          <col
            v-for="(column, index) in filteredColumns"
            :key="index"
            :style="column.width ? { width: column.width } : undefined"
          />
          <col v-if="$slots.actions" class="w-40" />
        </colgroup>

        <thead class="bg-gray-50" :class="{ 'ui-table-sticky-header': stickyHeader }">
          <tr>
            <!-- Expand Column -->
            <th v-if="expandable" class="ui-table-header-cell w-4 px-4 py-3">
              <span class="sr-only">Expand</span>
            </th>

            <!-- Bulk Selection Column -->
            <th v-if="bulkActionsEnabled" class="ui-table-header-cell w-4 px-4 py-3">
              <input
                type="checkbox"
                :checked="allSelected"
                :aria-label="allSelected ? 'Deselect all rows' : 'Select all rows'"
                :aria-checked="allSelected"
                class="rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                @change="toggleSelectAll"
              />
            </th>

            <!-- Column Headers -->
            <th
              v-for="(column, index) in filteredColumns"
              :key="index"
              class="ui-table-header-cell px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider"
              :class="column.headerClass"
            >
              <div class="flex items-center gap-2">
                <span>{{ column.label }}</span>

                <TableSortButton
                  v-if="column.sortable"
                  :column="column"
                  :sort-column="sortColumn"
                  :sort-direction="sortDirection"
                  @sort="handleSort"
                />
              </div>
            </th>

            <!-- Actions Column -->
            <th v-if="$slots.actions" class="ui-table-header-cell w-40 px-4 py-3">
              <span class="sr-only">Actions</span>
            </th>
          </tr>
        </thead>

        <tbody class="bg-white divide-y divide-gray-200">
          <TableSkeletonRows
            v-if="loading"
            :rows="skeletonRows"
            :columns="filteredColumns"
            :expandable="expandable"
            :selectable="bulkActionsEnabled"
            :has-actions="Boolean($slots.actions)"
          />

          <template v-else>
            <template v-for="(record, recordIndex) in visibleRecords" :key="recordIndex">
              <!-- Group heading, emitted whenever the grouped value changes -->
              <tr v-if="isGroupStart(recordIndex)" class="ui-table-group-row bg-gray-50">
                <td
                  :colspan="columnCount"
                  class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-600"
                >
                  {{ groupHeading(record) }}
                </td>
              </tr>

              <tr
                class="ui-table-row hover:bg-gray-50 transition-colors"
                :class="{
                  'bg-primary-50': isSelected(record) && stableFocusedRowIndex !== recordIndex,
                  'bg-primary-100 ring-2 ring-primary-500': stableFocusedRowIndex === recordIndex
                }"
                :tabindex="stableFocusedRowIndex === recordIndex ? 0 : -1"
                @focus="handleRowFocus(recordIndex)"
              >
                <!-- Expand Button Cell -->
                <td v-if="expandable" class="ui-table-cell px-4 py-3">
                  <button
                    type="button"
                    :aria-label="isExpanded(record) ? 'Collapse row' : 'Expand row'"
                    :aria-expanded="isExpanded(record)"
                    class="ui-table-expand-btn text-gray-400 hover:text-gray-600 transition-transform"
                    :class="{ 'rotate-90': isExpanded(record) }"
                    @click="toggleExpand(record)"
                  >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                  </button>
                </td>

                <!-- Bulk Selection Cell -->
                <td v-if="bulkActionsEnabled" class="ui-table-cell px-4 py-3">
                  <input
                    type="checkbox"
                    :checked="isSelected(record)"
                    :aria-label="`Select row ${recordIndex + 1}`"
                    :aria-checked="isSelected(record)"
                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                    @change="toggleSelect(record)"
                  />
                </td>

                <!-- Data Cells -->
                <td
                  v-for="(column, colIndex) in filteredColumns"
                  :key="colIndex"
                  class="ui-table-cell px-4 py-3 text-sm text-gray-900"
                  :class="column.cellClass"
                >
                  <slot
                    :name="`cell-${columnName(column)}`"
                    :record="record"
                    :value="cellValue(record, column)"
                    :tree-has-children="treeParents.has(record)"
                    :tree-collapsed="collapsedRecords.has(record)"
                    :tree-toggle="() => toggleCollapse(record)"
                  >
                    {{ cellValue(record, column) }}
                  </slot>
                </td>

                <!-- Actions Cell -->
                <td v-if="$slots.actions" class="ui-table-cell px-4 py-3 text-right text-sm">
                  <slot name="actions" :record="record" />
                </td>
              </tr>

              <!-- Expanded Row Content -->
              <tr v-if="expandable && isExpanded(record)" class="ui-table-expanded-row">
                <td :colspan="columnCount" class="px-4 py-4 bg-gray-50">
                  <div class="ui-table-expanded-content">
                    <slot name="expandedRow" :record="record">
                      <div class="text-sm text-gray-500">
                        No expanded content defined
                      </div>
                    </slot>
                  </div>
                </td>
              </tr>
            </template>

            <!-- Empty State -->
            <tr v-if="records.length === 0">
              <td :colspan="columnCount" class="px-4 py-12 text-center text-sm text-gray-500">
                <slot name="emptyState">
                  <TableEmptyState :title="emptyStateTitle" :description="emptyStateDescription" />
                </slot>
              </td>
            </tr>
          </template>
        </tbody>

        <!--
          Optional footer row (column summaries). Rendered inside the table so
          the cells line up with their columns; scoped by column so a consumer
          only fills the ones it has a value for.
        -->
        <tfoot v-if="$slots.summary" class="ui-table-summary bg-gray-50 border-t border-gray-200">
          <tr>
            <td v-if="expandable" class="px-4 py-3" />
            <td v-if="bulkActionsEnabled" class="px-4 py-3" />
            <td
              v-for="(column, colIndex) in filteredColumns"
              :key="`summary-${colIndex}`"
              class="ui-table-summary-cell px-4 py-3 text-sm text-gray-700"
              :class="column.cellClass"
            >
              <slot name="summary" :column="column" />
            </td>
            <td v-if="$slots.actions" class="px-4 py-3" />
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="$slots.pagination" class="ui-table-footer border-t border-gray-200 bg-white px-4 py-3">
      <slot name="pagination" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, useSlots, type PropType } from 'vue'
import TableToolbar from './TableToolbar.vue'
import TableSortButton from './TableSortButton.vue'
import TableSkeletonRows from './TableSkeletonRows.vue'
import TableEmptyState from './TableEmptyState.vue'
import { useTableSelection } from './useTableSelection'
import { useTableTree } from './useTableTree'
import { useTableGrouping } from './useTableGrouping'
import { useTableRowFocus } from './useTableRowFocus'
import { getPath } from './tableSchema'
import { columnName, type TableColumn, type TableRecord } from './tableTypes'

const props = defineProps({
  columns: {
    type: Array as PropType<TableColumn[]>,
    required: true,
  },
  records: {
    type: Array as PropType<TableRecord[]>,
    required: true,
  },
  searchable: {
    type: Boolean,
    default: true,
  },
  search: {
    type: String,
    default: '',
  },
  bulkActionsEnabled: {
    type: Boolean,
    default: false,
  },
  sortColumn: {
    type: String,
    default: null,
  },
  sortDirection: {
    type: String,
    default: 'asc',
  },
  visibleColumns: {
    type: Array as PropType<string[]>,
    default: null,
  },
  emptyStateTitle: {
    type: String,
    default: 'No records found',
  },
  emptyStateDescription: {
    type: String,
    default: 'Try adjusting your search or filter to find what you are looking for.',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  skeletonRows: {
    type: Number,
    default: 5,
  },
  stickyHeader: {
    type: Boolean,
    default: false,
  },
  expandable: {
    type: Boolean,
    default: false,
  },
  externalFocusedRowIndex: {
    type: Number,
    default: -1,
  },
  // Opt-in: lets records with a depth field (e.g. a page tree) be collapsed
  // by their parent row. Off by default so unrelated tables (Users, etc.)
  // are unaffected — pass true only where `records` actually forms a tree.
  treeCollapsible: {
    type: Boolean,
    default: false,
  },
  // Field on each record holding its nesting level (0 = top-level).
  treeDepthKey: {
    type: String,
    default: 'depth',
  },
  /**
   * Field (dot path allowed) whose value clusters rows under a heading.
   * The server is responsible for ordering by it; this only draws the break.
   */
  groupBy: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:search', 'sort', 'bulkAction', 'rowClick'])

const slots = useSlots()
const root = ref<HTMLElement | null>(null)

const filteredColumns = computed(() => {
  if (!props.visibleColumns || props.visibleColumns.length === 0) return props.columns

  return props.columns.filter((column) => props.visibleColumns.includes(columnName(column) ?? ''))
})

/** Header cells, which is also the colspan a full-width row has to fill. */
const columnCount = computed(
  () =>
    filteredColumns.value.length +
    (props.expandable ? 1 : 0) +
    (props.bulkActionsEnabled ? 1 : 0) +
    (slots.actions ? 1 : 0),
)

const { selectedRecords, allSelected, isSelected, toggleSelect, toggleSelectAll } =
  useTableSelection(() => props.records)

const {
  collapsedRecords,
  treeParents,
  showTreeToggle,
  allTreeCollapsed,
  visibleRecords,
  toggleCollapse,
  toggleAllTree,
} = useTableTree({
  enabled: () => props.treeCollapsible,
  records: () => props.records,
  depthKey: () => props.treeDepthKey,
})

const { isGroupStart, groupHeading } = useTableGrouping(
  () => props.groupBy,
  () => visibleRecords.value,
)

const { stableFocusedRowIndex, handleTableKeyDown, handleRowFocus } = useTableRowFocus({
  externalIndex: () => props.externalFocusedRowIndex,
  loading: () => props.loading,
  bulkActionsEnabled: () => props.bulkActionsEnabled,
  visibleRecords: () => visibleRecords.value,
  toggleSelect,
  onActivate: (record) => emit('rowClick', record),
  root: () => root.value,
})

function cellValue(record: TableRecord, column: TableColumn): unknown {
  const name = columnName(column)

  return name ? getPath(record, name) : undefined
}

function handleSort(name: string | undefined): void {
  const direction = props.sortColumn === name && props.sortDirection === 'asc' ? 'desc' : 'asc'

  emit('sort', { column: name, direction })
}

// ── Expandable rows ──────────────────────────────────────────────────────────

const expandedRecords = ref<TableRecord[]>([])

function isExpanded(record: TableRecord): boolean {
  return expandedRecords.value.includes(record)
}

function toggleExpand(record: TableRecord): void {
  const index = expandedRecords.value.indexOf(record)

  if (index > -1) {
    expandedRecords.value.splice(index, 1)
  } else {
    expandedRecords.value.push(record)
  }
}
</script>
