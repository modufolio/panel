<template>
  <div class="ui-drawer-relation-list" :class="bordered ? 'border-t border-gray-200 pt-4' : undefined">
    <!-- Heading + add action. Omitted entirely when the list *is* the tab. -->
    <div v-if="heading || $slots.actions || addable" class="mb-3 flex items-center justify-between">
      <h4 class="text-sm font-medium text-gray-700">{{ heading }}</h4>

      <div class="flex items-center gap-2">
        <slot name="actions" />
        <button
          v-if="addable"
          type="button"
          class="text-xs font-medium text-primary-600 hover:text-primary-800"
          @click="$emit('add')"
        >
          {{ addLabel }}
        </button>
      </div>
    </div>

    <slot v-if="items.length === 0" name="empty">
      <div class="text-sm text-gray-400" :class="dense ? 'py-2' : 'py-8 text-center'">
        {{ emptyText }}
      </div>
    </slot>

    <div v-else :class="variant === 'cards' ? 'space-y-2' : listContainerClass">
      <component
        :is="rowComponent(item)"
        v-for="(item, index) in items"
        :key="item.id ?? index"
        v-bind="rowBindings(item)"
        :class="rowClass"
        @click="onRowClick(item)"
      >
        <div class="flex min-w-0 items-center gap-3">
          <slot name="row" :item="item" :index="index" />
        </div>

        <div class="ml-2 flex shrink-0 items-center gap-2">
          <slot name="rowActions" :item="item" :index="index" />

          <button
            v-if="deletable"
            type="button"
            class="p-1 text-gray-400 transition-colors hover:text-red-500"
            :title="deleteLabel"
            @click.stop.prevent="$emit('delete', item)"
          >
            <Icon name="x" class="h-4 w-4" />
          </button>

          <Icon
            v-if="showChevron && href"
            name="chevron-right"
            class="h-4 w-4 text-gray-400"
          />
        </div>
      </component>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Icon from '../Core/Icon.vue'
import DrawerLink from './DrawerLink.vue'

/**
 * A compact list of related records inside a drawer: optional heading with an
 * add action, an empty state, and rows that drill into their own drawer frame.
 *
 * This is the small sibling of RelationManager — that one is a paginated,
 * API-backed table for a full page; this one is the few-rows-in-a-drawer
 * case that every detail view repeats.
 *
 * Row navigation has two honest modes, because the call sites genuinely
 * differ: `navigation: 'drawer'` pushes a frame onto the current stack (the
 * usual drill-down), while `'visit'` performs a full Inertia visit — needed
 * when the target's drawer slot lives on *another* page, which a drawer-only
 * visit would leave unrendered.
 */

/** One related row, as the server sends it; `id` keys the row when present. */
type RelationItem = Record<string, unknown>

const props = withDefaults(defineProps<{
  items?: RelationItem[]
  heading?: string
  emptyText?: string
  /** Builds the row's destination; rows are inert when omitted. */
  href?: (item: RelationItem) => string
  navigation?: 'drawer' | 'visit'
  queryParams?: Record<string, unknown>
  variant?: 'list' | 'cards'
  /** Show the "+ Add" affordance and emit `add`. */
  addable?: boolean
  addLabel?: string
  /** Show a per-row remove affordance and emit `delete`. */
  deletable?: boolean
  deleteLabel?: string
  /** Separator above the heading — for stacked sections in one tab. */
  bordered?: boolean
  /** Tight empty state, for a section among others rather than a whole tab. */
  dense?: boolean
  showChevron?: boolean
}>(), {
  items: () => [],
  heading: '',
  emptyText: 'Nothing here yet.',
  href: undefined,
  navigation: 'drawer',
  queryParams: () => ({}),
  variant: 'list',
  addable: false,
  addLabel: '+ Add',
  deletable: false,
  deleteLabel: 'Remove',
  bordered: false,
  dense: false,
  showChevron: true,
})

defineEmits<{
  add: []
  delete: [item: RelationItem]
}>()

const listContainerClass =
  'divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200'

const rowClass = computed(() => {
  const base = 'group flex w-full items-center justify-between text-left'

  if (props.variant === 'cards') {
    return `${base} rounded-lg border border-gray-200 bg-white px-3 py-3`
  }

  return `${base} px-3 py-2 ${props.href ? 'hover:bg-gray-50' : ''}`
})

/**
 * A drawer drill-down is a DrawerLink; a cross-page target is a button that
 * visits; an inert row is a plain div, so it never announces itself as
 * clickable to a screen reader.
 */
function rowComponent(_item: RelationItem) {
  if (!props.href) {
    return 'div'
  }

  return props.navigation === 'drawer' ? DrawerLink : 'button'
}

function rowBindings(item: RelationItem): Record<string, unknown> {
  if (!props.href) {
    return {}
  }

  if (props.navigation === 'drawer') {
    return {
      href: props.href(item),
      queryParams: props.queryParams,
      color: 'gray',
      showArrow: false,
    }
  }

  return { type: 'button' }
}

function onRowClick(item: RelationItem): void {
  if (props.href && props.navigation === 'visit') {
    router.visit(props.href(item))
  }
}
</script>
