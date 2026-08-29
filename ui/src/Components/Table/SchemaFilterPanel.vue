<template>
  <FilterPopover :active-filter-count="activeFilterCount" :width="480" @reset="$emit('reset')">
    <!-- Grouping is a filter-shaped control, so it reuses the same plumbing -->
    <SelectFilter
      v-if="groups.length > 0"
      label="Group by"
      placeholder="No grouping"
      :options="groups"
      :model-value="(values.group as string) ?? ''"
      @update:model-value="(value: unknown) => $emit('update:filter', 'group', value)"
    />

    <!-- Ad-hoc conditions: declared fields, operators from their type -->
    <div v-if="constraints.length > 0" class="border-b border-gray-200 pb-3">
      <QueryBuilder
        :constraints="constraints"
        :model-value="(values.constraints as QueryCondition[]) ?? []"
        @update:model-value="(value: QueryCondition[]) => $emit('update:filter', 'constraints', value)"
      />
    </div>

    <component
      v-for="filter in filters"
      :is="filterComponent(filter)"
      :key="filter.key"
      v-bind="filterProps(filter)"
      :model-value="values[filter.key] as any"
      @update:model-value="(value: unknown) => $emit('update:filter', filter.key, value)"
    />
  </FilterPopover>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import FilterPopover from '../Filters/FilterPopover.vue'
import SelectFilter from '../Filters/SelectFilter.vue'
import TernaryFilter from '../Filters/TernaryFilter.vue'
import MultiSelectFilter from '../Filters/MultiSelectFilter.vue'
import DateRangeFilter from '../Filters/DateRangeFilter.vue'
import QueryBuilder from '../Filters/QueryBuilder.vue'
import type { SchemaFilter, QueryCondition, TableSchema } from './tableSchema'

defineProps({
  filters: {
    type: Array as PropType<SchemaFilter[]>,
    default: () => [],
  },
  groups: {
    type: Array as PropType<NonNullable<TableSchema['groups']>>,
    default: () => [],
  },
  constraints: {
    type: Array as PropType<NonNullable<TableSchema['constraints']>>,
    default: () => [],
  },
  /** Current filter form values, keyed by filter key. */
  values: {
    type: Object as PropType<Record<string, unknown>>,
    default: () => ({}),
  },
  activeFilterCount: {
    type: Number,
    default: 0,
  },
})

defineEmits(['update:filter', 'reset'])

const filterComponentForType: Record<string, any> = {
  select: SelectFilter,
  multiSelect: MultiSelectFilter,
  ternary: TernaryFilter,
  trashed: TernaryFilter,
  dateRange: DateRangeFilter,
}

function filterComponent(filter: SchemaFilter): any {
  return filterComponentForType[filter.type] ?? SelectFilter
}

function filterProps(filter: SchemaFilter): Record<string, any> {
  const base: Record<string, any> = { label: filter.label }

  if (filter.placeholder) base.placeholder = filter.placeholder

  if (filter.type === 'ternary' || filter.type === 'trashed') {
    return {
      ...base,
      trueLabel: filter.trueLabel,
      falseLabel: filter.falseLabel,
      trueValue: filter.trueValue,
      falseValue: filter.falseValue,
    }
  }

  if (filter.type === 'select' || filter.type === 'multiSelect') {
    // optionsTruncated only arrives when the server capped the list; passing it
    // through is what keeps that bound visible instead of silent.
    return {
      ...base,
      options: filter.options ?? [],
      optionsTruncated: filter.optionsTruncated ?? false,
    }
  }

  return base
}
</script>
