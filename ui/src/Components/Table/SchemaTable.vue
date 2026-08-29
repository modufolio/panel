<template>
  <Table
    v-bind="tableProps"
    :columns="schema.columns"
    :records="records"
    :visible-columns="visibleColumns ?? undefined"
    :group-by="activeGroupField"
    @update:search="$emit('update:search', $event)"
    @sort="$emit('sort', $event)"
    @row-click="$emit('rowClick', $event)"
  >
    <!--
      Forward every slot the consumer passed that we don't generate ourselves:
      header, headerActions, filters, actions, bulkActions, pagination, and any
      #cell-{key} the page wants to override.
    -->
    <template v-for="name in passthroughSlots" :key="name" #[name]="scope">
      <slot :name="name" v-bind="scope ?? {}" />
    </template>

    <template v-if="!slots.filterIndicators" #filterIndicators>
      <FilterIndicators
        :indicators="filterIndicators"
        @remove="clearFilter"
        @clear="resetFilters"
      />
    </template>

    <!--
      Filters, rendered from the schema. A page-supplied #filters slot wins,
      so anything the declarative types cannot express stays possible.
    -->
    <template v-if="hasFilterControls && !slots.filters" #filters>
      <SchemaFilterPanel
        :filters="schema.filters ?? []"
        :groups="schema.groups ?? []"
        :constraints="schema.constraints ?? []"
        :values="filterValues"
        :active-filter-count="activeFilterCount"
        @update:filter="(key: string, value: unknown) => $emit('update:filter', key, value)"
        @reset="resetFilters"
      />
    </template>

    <!-- Column summaries, computed server-side over the filtered set -->
    <template v-if="hasSummaries" #summary="{ column }">
      <div
        v-for="summary in summariesFor(column as SchemaColumn)"
        :key="summary.type"
        class="whitespace-nowrap"
        :class="cellClasses({ ...(column as SchemaColumn), align: (column as SchemaColumn).align ?? 'right' })"
      >
        <span class="text-xs text-gray-500">{{ summary.label }}</span>
        <span class="ml-1 font-medium">{{ formatSummary(column as SchemaColumn, summary) }}</span>
      </div>
    </template>

    <!--
      Row actions from the schema. The page's own #actions slot wins — it is
      forwarded above as a passthrough slot — so adopting declared actions is
      additive for every listing that already writes its own.
    -->
    <template v-if="generatedRowActions" #actions="{ record }">
      <ActionGroup label="Actions">
        <ActionGroupItem
          v-for="action in rowActionsFor(asRecord(record))"
          :key="action.name"
          :icon="action.icon"
          :label="action.label"
          :color="action.color"
          @click="runRowAction(action, asRecord(record))"
        />
      </ActionGroup>
    </template>

    <!-- Actions on a selection, same rule: a page-supplied slot wins. -->
    <template v-if="generatedBulkActions" #bulkActions="{ selected }">
      <Action
        v-for="action in (schema.bulkActionItems ?? [])"
        :key="action.name"
        :label="action.label"
        :icon="action.icon"
        :color="action.color"
        :variant="action.variant ?? 'solid'"
        size="sm"
        @click="runBulkAction(action, selected as Record<string, any>[])"
      />
    </template>

    <!-- Generic cell rendering, one per schema column -->
    <template
      v-for="column in generatedColumns"
      :key="column.key"
      #[`cell-${column.key}`]="{ record }"
    >
      <!--
        The wrapper only becomes a flex row when the column declares actions,
        so cells without them keep exactly the layout they had.
      -->
      <div :class="column.actions?.length ? 'flex items-center gap-2' : undefined">
        <!-- Table.vue's slot scope is untyped, so narrow once via asRecord(). -->
        <component
          :is="cellLink(column, asRecord(record), schema.recordUrl) ? DrawerLink : 'div'"
          v-bind="cellLinkProps(column, asRecord(record), schema.recordUrl, queryParams)"
          :class="column.actions?.length ? 'min-w-0 flex-1 truncate' : undefined"
        >
          <SchemaCell
            :column="column"
            :record="asRecord(record)"
            :value="cellValue(column, asRecord(record))"
            :handler="cellHandlers[column.key]"
          />
        </component>

        <CellActions
          v-if="column.actions?.length"
          :column="column"
          :record="asRecord(record)"
          :handlers="cellActionHandlers"
        />
      </div>
    </template>
  </Table>

  <!--
    The confirmations the declared actions need. Owned here rather than by
    each page: an action that says `confirm` should not require the page to
    remember to render a dialog for it.
  -->
  <DeleteConfirmDialog
    :state="deletion.state"
    :label="deleteLabel"
    @confirm="deletion.confirm"
    @close="deletion.close"
  />

  <ConfirmDialog
    v-if="pendingBulk"
    :is-open="true"
    :title="pendingBulk.action.label"
    :message="bulkMessage"
    :confirm-label="pendingBulk.action.label"
    @close="pendingBulk = null"
    @confirm="confirmBulk"
  />
</template>

<script setup lang="ts">
import { computed, useSlots, type PropType } from 'vue'
import Table from './Table.vue'
import SchemaCell from './SchemaCell'
import SchemaFilterPanel from './SchemaFilterPanel.vue'
import Action from '../Actions/Action.vue'
import ActionGroup from '../Actions/ActionGroup.vue'
import ActionGroupItem from '../Actions/ActionGroupItem.vue'
import ConfirmDialog from '../Dialogs/ConfirmDialog.vue'
import DeleteConfirmDialog from '../Dialogs/DeleteConfirmDialog.vue'
import DrawerLink from '../Drawer/DrawerLink.vue'
import CellActions from '../Columns/CellActions.vue'
import FilterIndicators from '../Filters/FilterIndicators.vue'
import { useFocusedStackRow } from '../Drawer/useFocusedStackRow'
import type { StackItem } from '../Drawer/useDrawerStack'
import { useSchemaFilters } from './useSchemaFilters'
import { useSchemaActions } from './useSchemaActions'
import { cellValue, cellLink, cellLinkProps } from './schemaLinks'
import {
  cellClasses,
  formatValue,
  type SchemaColumn,
  type SchemaColumnAction,
  type SchemaRowAction,
  type SchemaBulkAction,
  type TableSchema,
} from './tableSchema'

const props = defineProps({
  /** Server-authored schema — see App\Table\TableSchema. */
  schema: {
    type: Object as PropType<TableSchema>,
    required: true,
  },
  records: {
    type: Array as PropType<Record<string, any>[]>,
    default: () => [],
  },
  search: {
    type: String,
    default: '',
  },
  sortColumn: {
    type: String,
    default: null,
  },
  sortDirection: {
    type: String,
    default: 'asc',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  /** Appended to record links so a drawer preserves the current list state. */
  queryParams: {
    type: Object as PropType<Record<string, any>>,
    default: () => ({}),
  },
  /** Null means "use the schema's toggleable defaults". */
  visibleColumns: {
    type: Array as PropType<string[] | null>,
    default: null,
  },
  /** Handlers for row actions the schema declares but the table cannot service. */
  rowActionHandlers: {
    type: Object as PropType<Record<string, (record: any, action: SchemaRowAction) => unknown>>,
    default: () => ({}),
  },
  /** The same, for bulk actions. */
  bulkActionHandlers: {
    type: Object as PropType<Record<string, (records: any[], action: SchemaBulkAction) => unknown>>,
    default: () => ({}),
  },
  externalFocusedRowIndex: {
    type: Number,
    default: -1,
  },
  /**
   * The page's drawer stack. When given, the highlighted row follows the
   * record the top drawer is showing (arrow-key record pagination changes
   * the stack without a click). `externalFocusedRowIndex` overrides this.
   */
  stack: {
    type: Array as PropType<StackItem[]>,
    default: () => [],
  },
  /** Restrict stack-driven highlighting to items of this drawer type. */
  drawerType: {
    type: String,
    default: undefined,
  },
  /**
   * Save handlers for `editable` columns, keyed by column key.
   *
   * The schema can declare that a column is editable and what its choices
   * are, but not how to persist a change — a callback cannot be serialised
   * into an Inertia prop. The page injects that behaviour here.
   */
  cellHandlers: {
    type: Object as PropType<Record<string, (record: any, column: string, value: any) => unknown>>,
    default: () => ({}),
  },
  /**
   * Handlers for a column's cell `actions`, keyed by action name.
   *
   * Same reason as `cellHandlers`: the schema declares that an action exists
   * and how it looks, but what it *does* cannot cross the prop boundary.
   * Actions that navigate declare a `urlTemplate` instead and need no entry.
   */
  cellActionHandlers: {
    type: Object as PropType<Record<string, (record: any, action: SchemaColumnAction) => unknown>>,
    default: () => ({}),
  },
  /** Current filter form values, keyed by filter key. */
  filterValues: {
    type: Object as PropType<Record<string, unknown>>,
    default: () => ({}),
  },
  /**
   * Server-computed aggregates keyed by column key, from the collection's
   * `meta.summaries`. Not part of the schema — they change with the filters.
   */
  summaries: {
    type: Object as PropType<Record<string, Array<{ type: string; label: string; value: number | null }>>>,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:search', 'sort', 'rowClick', 'update:filter', 'resetFilters'])

const slots = useSlots()

const stackFocusedRowIndex = useFocusedStackRow(props, props.drawerType, () => props.records)

/** Schema-driven table settings, with the page still able to override. */
const tableProps = computed(() => ({
  searchable: props.schema.searchable ?? true,
  search: props.search,
  bulkActionsEnabled: props.schema.bulkActions ?? false,
  stickyHeader: props.schema.stickyHeader ?? true,
  sortColumn: props.sortColumn,
  sortDirection: props.sortDirection,
  loading: props.loading,
  externalFocusedRowIndex:
    props.externalFocusedRowIndex >= 0 ? props.externalFocusedRowIndex : stackFocusedRowIndex.value,
  emptyStateTitle: props.schema.emptyStateTitle ?? undefined,
  emptyStateDescription: props.schema.emptyStateDescription ?? undefined,
}))

/**
 * Columns we render generically — i.e. those the page has NOT overridden with
 * its own `#cell-{key}`. An override wins, which is the escape hatch for
 * anything the declarative schema cannot express.
 */
const generatedColumns = computed(() =>
  props.schema.columns.filter((column) => !slots[`cell-${column.key}`]),
)

/** Every consumer slot, minus the cell slots we generate. */
const passthroughSlots = computed(() =>
  Object.keys(slots).filter(
    (name) => !generatedColumns.value.some((column) => `cell-${column.key}` === name),
  ),
)

/** Table.vue passes its slot scope untyped; every use narrows through here. */
function asRecord(record: unknown): Record<string, any> {
  return (record ?? {}) as Record<string, any>
}

// ── Summaries ────────────────────────────────────────────────────────────────

const hasSummaries = computed(() => Object.keys(props.summaries ?? {}).length > 0)

function summariesFor(column: SchemaColumn) {
  return props.summaries?.[column.key] ?? []
}

/** A summary reuses its column's formatting, except counts, which are integers. */
function formatSummary(column: SchemaColumn, summary: { type: string; value: number | null }): string {
  if (summary.value === null) return '—'
  if (summary.type === 'count') return String(summary.value)

  return formatValue(column, summary.value)
}

// ── Filters ──────────────────────────────────────────────────────────────────

const {
  activeGroupField,
  hasFilterControls,
  activeFilterCount,
  filterIndicators,
  clearFilter,
  resetFilters,
} = useSchemaFilters({
  schema: () => props.schema,
  values: () => props.filterValues,
  onChange: (key, value) => emit('update:filter', key, value),
  onReset: () => emit('resetFilters'),
})

// ── Declared actions ─────────────────────────────────────────────────────────

const {
  rowActionsFor,
  runRowAction,
  deletion,
  deleteLabel,
  runBulkAction,
  pendingBulk,
  bulkMessage,
  confirmBulk,
} = useSchemaActions({
  schema: () => props.schema,
  queryParams: () => props.queryParams,
  recordLabel: () => props.drawerType,
  rowActionHandlers: () => props.rowActionHandlers,
  bulkActionHandlers: () => props.bulkActionHandlers,
})

/** Generate the row menu only when the page did not write its own. */
const generatedRowActions = computed(
  () => !slots.actions && (props.schema.actions ?? []).length > 0,
)

const generatedBulkActions = computed(
  () => !slots.bulkActions && (props.schema.bulkActionItems ?? []).length > 0,
)
</script>
