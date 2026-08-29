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
      class="ui-cell-action inline-flex items-center justify-center rounded-xs p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-600 aria-disabled:pointer-events-none aria-disabled:opacity-40 disabled:pointer-events-none disabled:opacity-40"
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
import { getPath, resolveRecordUrl, visibleCellActions, type SchemaColumn, type SchemaColumnAction } from '../Table/tableSchema'

type ActionHandler = (record: Record<string, any>, action: SchemaColumnAction) => unknown

const props = defineProps({
  column: { type: Object as PropType<SchemaColumn>, required: true },
  record: { type: Object as PropType<Record<string, any>>, required: true },
  handlers: { type: Object as PropType<Record<string, ActionHandler>>, default: () => ({}) },
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
    primary: 'hover:text-primary-700 hover:bg-primary-50',
    success: 'hover:text-success-700 hover:bg-success-50',
    danger: 'hover:text-danger-700 hover:bg-danger-50',
    warning: 'hover:text-warning-700 hover:bg-warning-50',
    info: 'hover:text-info-700 hover:bg-info-50',
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
