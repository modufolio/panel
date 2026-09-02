<template>
  <section class="ui-child-table">
    <header class="flex items-baseline gap-2 px-1 pb-2">
      <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ child.label }}</h4>
      <span class="ui-child-table-count text-xs text-gray-400" aria-label="rows">{{ rows.length }}</span>
    </header>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
      <Table
        nested
        :columns="child.columns"
        :records="rows"
        :visible-columns="visibleColumns"
        :searchable="false"
        :sticky-header="false"
        :empty-state-title="child.empty ?? 'Nothing to show'"
        empty-state-description=""
      >
        <template
          v-for="column in child.columns"
          :key="column.key"
          #[`cell-${column.key}`]="{ record }"
        >
          <component
            :is="cellLink(column, asRecord(record), recordUrl) ? DrawerLink : 'div'"
            v-bind="cellLinkProps(column, asRecord(record), recordUrl, queryParams)"
          >
            <SchemaCell
              :column="column"
              :record="asRecord(record)"
              :value="cellValue(column, asRecord(record))"
            />
          </component>
        </template>
      </Table>
    </div>
  </section>
</template>

<script setup lang="ts">
/**
 * The nested table under an expanded parent row: one declared child, its
 * rows read from the parent row under `child.source`, its cells rendered by
 * the same SchemaCell and link resolution the parent table uses.
 *
 * Nothing here fetches. The server declared the child and loaded its rows
 * with the page; a child's `recordUrl` may name `{parent}` to link into the
 * parent's own record, the drawer-tab convention.
 */
import { computed, type PropType } from 'vue'
import Table from './Table.vue'
import SchemaCell from './SchemaCell'
import DrawerLink from '../Drawer/DrawerLink.vue'
import { cellLink, cellLinkProps, cellValue } from './schemaLinks'
import type { SchemaChildTable } from './tableSchema'
import type { TableRecord } from './tableTypes'

const props = defineProps({
  child: { type: Object as PropType<SchemaChildTable>, required: true },
  /** The expanded parent row. */
  record: { type: Object as PropType<TableRecord>, required: true },
  queryParams: { type: Object as PropType<Record<string, unknown>>, default: () => ({}) },
})

const rows = computed<TableRecord[]>(() => {
  const value = props.record[props.child.source]

  return Array.isArray(value) ? value.map(asRecord) : []
})

const visibleColumns = computed(() =>
  props.child.columns.filter((column) => !column.hiddenByDefault).map((column) => column.key),
)

/** The child's template with the parent's id substituted, the rest resolving per child row. */
const recordUrl = computed<string | null>(() => {
  if (!props.child.recordUrl) return null

  const parentId = props.record.id
  const id = typeof parentId === 'string' || typeof parentId === 'number' ? String(parentId) : ''

  return props.child.recordUrl.replace(/\{parent\}/g, id)
})

/** Table.vue passes its slot scope untyped; every use narrows through here. */
function asRecord(record: unknown): TableRecord {
  return typeof record === 'object' && record !== null ? (record as TableRecord) : {}
}
</script>
