<template>
  <FieldPrimitive
    v-bind="{ width, label, help, error, required }"
    wrapper-class="ui-field-repeater border-0 p-0 m-0"
    as="fieldset"
  >
    <!-- Visible in blueprint mode — the rows carry their own sub-labels, but
         the collection itself still needs a name; screen-reader-only in the
         legacy layouts, whose consumers render their own headings. -->

    <!--
      Blueprint mode: sub-fields arrive as serialized declarations (from the
      server's HasManyType), rendered through the same field registry the
      surrounding BlueprintForm uses. Rows carry the child's uuid as `id`; new
      rows carry none, which is how the server tells update from create.
    -->
    <div v-if="fields.length > 0" class="ui-repeater-blueprint space-y-3">
      <div
        v-for="(item, index) in items"
        :key="rowKey(item, index, `new-${index}`)"
        class="ui-repeater-item relative rounded-lg border border-gray-300 bg-white p-4 shadow-sm"
      >
        <div class="grid grid-cols-12 gap-4 pr-16">
          <component
            :is="subComponent(subField.type)"
            v-for="subField in fields"
            :key="subField.key"
            :model-value="item[subField.key]"
            :label="subField.label"
            :width="subField.width ?? 'full'"
            :required="subField.required ?? false"
            :error="nestedErrors[`${index}.${subField.key}`] ?? ''"
            v-bind="{ ...(subField.options ? { options: subField.options } : {}), ...(subField.props ?? {}) }"
            @update:model-value="(val: unknown) => updateItem(index, subField.key, val)"
          />
        </div>

        <div class="absolute top-3 right-3 flex items-center gap-1">
          <button
            type="button"
            class="rounded p-1 text-gray-400 hover:text-gray-700 disabled:opacity-30"
            :disabled="disabled || index === 0"
            title="Move up"
            @click="moveItem(index, -1)"
          >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.47 6.47a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 1 1-1.06 1.06L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25Z" clip-rule="evenodd" /></svg>
          </button>
          <button
            type="button"
            class="rounded p-1 text-gray-400 hover:text-gray-700 disabled:opacity-30"
            :disabled="disabled || index === items.length - 1"
            title="Move down"
            @click="moveItem(index, 1)"
          >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.53 13.53a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 1.06-1.06L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25Z" clip-rule="evenodd" /></svg>
          </button>
          <button
            type="button"
            class="rounded p-1 text-danger-600 hover:text-danger-800 disabled:opacity-30"
            :disabled="disabled"
            title="Delete"
            @click="deleteItem(index)"
          >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>
          </button>
        </div>
      </div>

      <div v-if="items.length === 0" class="rounded-lg border border-dashed border-gray-300 py-6 text-center text-sm text-gray-500">
        {{ emptyMessage }}
      </div>
    </div>

    <!-- Table Repeater -->
    <div v-else-if="layout === 'table'" class="ui-repeater-table overflow-x-auto rounded-lg border border-gray-300 shadow-sm">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th
              v-for="(column, index) in columns"
              :key="index"
              class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              :style="column.width ? `width: ${column.width}` : ''"
            >
              {{ column.label }}
              <span v-if="column.required" class="text-danger-600">*</span>
            </th>
            <th class="w-16"></th>
          </tr>
        </thead>

        <tbody class="bg-white divide-y divide-gray-200">
          <tr
            v-for="(item, index) in items"
            :key="rowKey(item, index)"
            class="hover:bg-gray-50 transition-colors"
          >
            <td
              v-for="(column, colIndex) in columns"
              :key="colIndex"
              class="px-4 py-3 whitespace-nowrap"
            >
              <slot
                :name="`item-${column.name}`"
                :item="item"
                :index="index"
                :update="(value: unknown) => updateItem(index, column.name, value)"
              >
                <component
                  v-if="column.component"
                  :is="column.component"
                  :model-value="item[column.name]"
                  @update:model-value="updateItem(index, column.name, $event)"
                  v-bind="column.componentProps || {}"
                />
                <span v-else>{{ item[column.name] }}</span>
              </slot>
            </td>
            <td class="px-4 py-3 text-right">
              <button
                type="button"
                class="text-danger-600 hover:text-danger-900 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="disabled"
                @click="deleteItem(index)"
                title="Delete"
              >
                <svg
                  class="w-5 h-5"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z"
                    clip-rule="evenodd"
                  />
                </svg>
              </button>
            </td>
          </tr>

          <!-- Empty State -->
          <tr v-if="items.length === 0">
            <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-gray-500">
              {{ emptyMessage }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Simple Repeater (not table) -->
    <div v-else class="ui-repeater-simple space-y-4">
      <div
        v-for="(item, index) in items"
        :key="rowKey(item, index)"
        class="ui-repeater-item relative rounded-lg border border-gray-300 bg-white p-4 shadow-sm"
      >
        <button
          type="button"
          class="absolute top-4 right-4 text-danger-600 hover:text-danger-900 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="disabled"
          @click="deleteItem(index)"
          title="Delete"
        >
          <svg
            class="w-5 h-5"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z"
              clip-rule="evenodd"
            />
          </svg>
        </button>

        <slot :item="item" :index="index" />
      </div>

      <!-- Empty State -->
      <div v-if="items.length === 0" class="text-center py-8 text-gray-500">
        {{ emptyMessage }}
      </div>
    </div>

    <!-- Add Button -->
    <div class="ui-repeater-add mt-4">
      <button
        type="button"
        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        :disabled="disabled || !!(maxItems && items.length >= maxItems)"
        @click="addItem"
      >
        <svg
          class="w-5 h-5 mr-2 -ml-1"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"
          />
        </svg>
        {{ addButtonLabel }}
      </button>
    </div>
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent, type Component, type PropType } from 'vue'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'
// From the registry module, not useBlueprint — the composable imports the
// registry too, and importing it back from here re-creates the cycle the
// registry was extracted to break.
import { resolveFieldComponent } from './fieldRegistry'
import type { FieldDef } from './useBlueprint'

/** One row of the repeater; its keys are the consumer's columns or sub-fields. */
type RepeaterItem = Record<string, unknown>

/** A column of the table layout. */
interface RepeaterColumn {
  /** Key on each row the column reads and writes. */
  name: string
  label?: string
  /** CSS width for the header cell. */
  width?: string
  required?: boolean
  /** Editor rendered in the cell; the raw value is shown as text when absent. */
  component?: Component | string
  componentProps?: Record<string, unknown>
}

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: Array as PropType<RepeaterItem[]>,
    default: () => [],
  },
  label: {
    type: String,
    default: '',
  },
  help: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  // For table layout
  columns: {
    type: Array as PropType<RepeaterColumn[]>,
    default: () => [],
  },
  // Layout type: 'table' or 'simple'
  layout: {
    type: String,
    default: 'table',
    validator: (value: unknown) => ['table', 'simple'].includes(value as string),
  },
  // Default data for new items
  defaultItem: {
    type: [Object, Function],
    default: () => ({}),
  },
  // Add button label
  addButtonLabel: {
    type: String,
    default: 'Add item',
  },
  // Empty message
  emptyMessage: {
    type: String,
    default: 'No items yet',
  },
  // Max items
  maxItems: {
    type: Number,
    default: null,
  },
  required: {
    type: Boolean,
    default: false,
  },
  /** Blueprint mode: serialized sub-field declarations from the server. */
  fields: {
    type: Array as PropType<FieldDef[]>,
    default: () => [],
  },
  /** Row-scoped errors, keyed `{index}.{subKey}` (prefix already stripped). */
  nestedErrors: {
    type: Object as PropType<Record<string, string>>,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:modelValue'])


const items = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

// Sub-components resolve through the same registry BlueprintForm uses,
// cached per type so each is only wrapped once.
const subComponents: Record<string, Component> = {}

function subComponent(type: string): Component {
  return (subComponents[type] ??= defineAsyncComponent(() => resolveFieldComponent(type)))
}

function addItem() {
  // Blueprint mode: a fresh row is the declared keys, empty, and carries no
  // id — the absence of one is what tells the server "create, don't update".
  if (props.fields.length > 0) {
    const row: Record<string, unknown> = {}
    for (const field of props.fields) {
      row[field.key] = null
    }
    items.value = [...items.value, row]
    return
  }

  const newItem =
    typeof props.defaultItem === 'function'
      ? props.defaultItem()
      : { ...props.defaultItem }

  // Add a unique ID
  newItem.id = `item-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

  items.value = [...items.value, newItem]
}

/** A stable row identity: the item's id when it has one, its position otherwise. */
function rowKey(item: RepeaterItem, index: number, fallback: string | number = index): string | number {
  const id = item.id
  return (typeof id === 'string' || typeof id === 'number') && id ? id : fallback
}

function moveItem(index: number, delta: number) {
  const target = index + delta
  if (target < 0 || target >= items.value.length) return

  const next = [...items.value]
  ;[next[index], next[target]] = [next[target]!, next[index]!]
  items.value = next
}

function deleteItem(index: number) {
  items.value = items.value.filter((_, i) => i !== index)
}

function updateItem(index: number, field: string, value: unknown) {
  const updatedItems = [...items.value]
  updatedItems[index] = {
    ...updatedItems[index],
    [field]: value,
  }
  items.value = updatedItems
}
</script>
