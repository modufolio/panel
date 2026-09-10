<template>
  <span v-if="actions.length" class="ui-cell-actions inline-flex items-center gap-0.5 shrink-0">
    <component
      v-for="action in actions"
      :key="action.name"
      :is="hrefFor(action) ? 'a' : 'button'"
      v-bind="hrefFor(action) ? { href: hrefFor(action) } : { type: 'button' }"
      :title="action.label"
      :aria-label="action.label"
      :disabled="isDisabled(action) || undefined"
      :aria-disabled="isDisabled(action) || undefined"
      class="ui-cell-action inline-flex items-center justify-center rounded-xs p-1 text-ink-3 transition-colors hover:bg-hover hover:text-ink focus:outline-none focus:ring-2 focus:ring-focus aria-disabled:pointer-events-none aria-disabled:opacity-40 disabled:pointer-events-none disabled:opacity-40"
      :class="colorClass(action)"
      @click="onActivate(action, $event)"
    >
      <Icon v-if="action.icon" :name="action.icon" class="w-5 h-5" />
      <span v-else class="text-xs">{{ action.label }}</span>
    </component>
  </span>
</template>

<script setup lang="ts">
/**
 * Action buttons for one cell, declared by the column's schema.
 *
 * An action with a `urlTemplate` renders as a link; everything else is a button
 * that calls the handler the page registered under the action's name. Nothing
 * happens if no handler is registered — a schema can outlive the page that
 * knows how to service it, and a dead button is better than a thrown error.
 */
import { computed, type PropType } from 'vue'
import Icon from '../Core/Icon.vue'
import {
  getPath,
  resolveRecordUrl,
  visibleCellActions,
  type SchemaColumn,
  type SchemaColumnAction,
  type CellActionHandler,
} from '../Table/tableSchema'
import type { TableRecord } from '../Table/tableTypes'

const props = defineProps({
  column: { type: Object as PropType<SchemaColumn>, required: true },
  record: { type: Object as PropType<TableRecord>, required: true },
  handlers: { type: Object as PropType<Record<string, CellActionHandler>>, default: () => ({}) },
})

const actions = computed(() => visibleCellActions(props.column, props.record))

function hrefFor(action: SchemaColumnAction): string | null {
  if (!action.urlTemplate || isDisabled(action)) return null

  return resolveRecordUrl(action.urlTemplate, props.record)
}

function isDisabled(action: SchemaColumnAction): boolean {
  return action.disabledWhen ? Boolean(getPath(props.record, action.disabledWhen)) : false
}

function colorClass(action: SchemaColumnAction): string {
  const colors: Record<string, string> = {
    primary: 'hover:text-primary-on-surface hover:bg-primary-surface',
    success: 'hover:text-success-on-surface hover:bg-success-surface',
    danger: 'hover:text-danger-on-surface hover:bg-danger-surface',
    warning: 'hover:text-warning-on-surface hover:bg-warning-surface',
    info: 'hover:text-info-on-surface hover:bg-info-surface',
  }

  return action.color ? (colors[action.color] ?? '') : ''
}

function onActivate(action: SchemaColumnAction, event: MouseEvent) {
  if (isDisabled(action)) return

  // The cell may sit inside a record link; an action is its own intent.
  event.stopPropagation()

  if (action.urlTemplate) return

  event.preventDefault()

  if (action.confirm && !window.confirm(action.confirmMessage ?? `${action.label}?`)) return

  props.handlers[action.name]?.(props.record, action)
}
</script>
