<template>
  <section class="ui-drawer-relation-table" :class="bordered ? 'border-t border-gray-200 pt-4' : undefined">
    <div v-if="heading || addable" class="mb-3 flex items-center justify-between">
      <h4 class="text-sm font-medium text-gray-700">
        {{ heading }}
        <span v-if="rows.length" class="ml-1 text-xs font-normal text-gray-400" aria-label="rows">{{ rows.length }}</span>
      </h4>
      <button
        v-if="addable"
        type="button"
        class="text-xs font-medium text-primary-600 hover:text-primary-800"
        @click="$emit('add')"
      >
        {{ addLabel }}
      </button>
    </div>

    <div v-if="rows.length === 0" class="py-8 text-center text-sm text-gray-400">
      {{ emptyText }}
    </div>

    <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white">
      <Table
        nested
        :columns="columns"
        :records="rows"
        :visible-columns="visibleColumns"
        :searchable="false"
        :sticky-header="false"
        empty-state-title=""
        empty-state-description=""
      >
        <template
          v-for="column in columns"
          :key="column.key"
          #[`cell-${column.key}`]="{ record }"
        >
          <component
            :is="href ? (navigation === 'visit' ? 'a' : DrawerLink) : 'div'"
            v-bind="linkProps(asRecord(record))"
            class="block"
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
 * Related records as a table inside a drawer: a header row and one row per
 * record, cells rendered by the same SchemaCell the listing uses, so a
 * project's tasks show their state and due date at a glance instead of a
 * title with one line underneath. The counterpart of ChildTable for the
 * drawer; DrawerRelationList remains the two-line form.
 *
 * Nothing here fetches: the rows came with the frame. Every cell links to
 * the row's record when the tab names a recordUrl, in the drawer or as a
 * full visit, exactly as the list rows do.
 */
import { computed, type PropType } from 'vue'
import { router } from '@inertiajs/vue3'
import Table from '../Table/Table.vue'
import SchemaCell from '../Table/SchemaCell'
import DrawerLink from './DrawerLink.vue'
import { cellValue } from '../Table/schemaLinks'
import type { SchemaColumn } from '../Table/tableSchema'
import type { TableRecord } from '../Table/tableTypes'

type RelationRow = Record<string, unknown>

const props = defineProps({
  heading: { type: String, default: '' },
  columns: { type: Array as PropType<SchemaColumn[]>, required: true },
  rows: { type: Array as PropType<RelationRow[]>, default: () => [] },
  emptyText: { type: String, default: 'Nothing here yet.' },
  addable: { type: Boolean, default: false },
  addLabel: { type: String, default: '+ Add' },
  href: { type: Function as PropType<(row: RelationRow) => string>, default: undefined },
  navigation: { type: String as PropType<'drawer' | 'visit'>, default: 'drawer' },
  bordered: { type: Boolean, default: false },
})

defineEmits<{ (e: 'add'): void }>()

const visibleColumns = computed(() => props.columns.map((column) => column.key))

function asRecord(record: unknown): TableRecord {
  return record as TableRecord
}

function linkProps(row: TableRecord): Record<string, unknown> {
  if (!props.href) return {}

  const target = props.href(row)

  if (props.navigation === 'visit') {
    return {
      href: target,
      onClick: (event: MouseEvent) => {
        event.preventDefault()
        router.visit(target)
      },
    }
  }

  return { href: target }
}
</script>
