<template>
  <div class="ui-query-builder space-y-3">
    <div v-if="modelValue.length === 0" class="text-sm text-gray-400">
      No conditions yet.
    </div>

    <div
      v-for="(condition, index) in modelValue"
      :key="index"
      class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
    >
      <span class="text-xs font-medium text-gray-500">{{ index === 0 ? 'Where' : 'And' }}</span>

      <!-- Field -->
      <select
        :value="condition.key"
        class="ui-input"
        :aria-label="`Condition ${index + 1} field`"
        @change="changeField(index, ($event.target as HTMLSelectElement).value)"
      >
        <option v-for="constraint in constraints" :key="constraint.key" :value="constraint.key">
          {{ constraint.label }}
        </option>
      </select>

      <!-- Operator -->
      <select
        :value="condition.operator"
        class="ui-input"
        :aria-label="`Condition ${index + 1} operator`"
        @change="update(index, { operator: ($event.target as HTMLSelectElement).value })"
      >
        <option
          v-for="operator in operatorsFor(condition.key)"
          :key="operator.value"
          :value="operator.value"
        >
          {{ operator.label }}
        </option>
      </select>

      <!-- Value(s) — arity comes from the operator, so a nullary one shows none -->
      <!-- A boolean's one operator is `is`, which takes a value: two choices,
           not free text. -->
      <select
        v-if="arity(condition) >= 1 && constraintFor(condition.key)?.type === 'boolean'"
        :value="String(condition.value ?? '1')"
        class="ui-input w-40"
        :aria-label="`Condition ${index + 1} value`"
        @change="update(index, { value: ($event.target as HTMLSelectElement).value })"
      >
        <option value="1">Yes</option>
        <option value="0">No</option>
      </select>

      <input
        v-else-if="arity(condition) >= 1"
        :type="inputType(condition.key)"
        :value="condition.value ?? ''"
        class="w-40 rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"
        :aria-label="`Condition ${index + 1} value`"
        @input="update(index, { value: ($event.target as HTMLInputElement).value })"
      />

      <template v-if="arity(condition) === 2">
        <span class="text-xs text-gray-500">and</span>
        <input
          :type="inputType(condition.key)"
          :value="condition.value2 ?? ''"
          class="ui-input w-40"
          :aria-label="`Condition ${index + 1} second value`"
          @input="update(index, { value2: ($event.target as HTMLInputElement).value })"
        />
      </template>

      <button
        type="button"
        class="ml-auto rounded p-1 text-gray-400 hover:text-danger-600"
        :aria-label="`Remove condition ${index + 1}`"
        @click="remove(index)"
      >
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <button
      type="button"
      class="text-sm font-medium text-primary-600 hover:text-primary-800"
      @click="add"
    >
      + Add condition
    </button>
  </div>
</template>

<script setup lang="ts">
import { type PropType } from 'vue'
import type { SchemaConstraint, QueryCondition } from '../Table/tableSchema'

const props = defineProps({
  /** Fields the schema allows conditions against. */
  constraints: {
    type: Array as PropType<SchemaConstraint[]>,
    default: () => [],
  },
  modelValue: {
    type: Array as PropType<QueryCondition[]>,
    default: () => [],
  },
})

const emit = defineEmits<{ 'update:modelValue': [QueryCondition[]] }>()

function constraintFor(key: string): SchemaConstraint | undefined {
  return props.constraints.find((constraint) => constraint.key === key)
}

function operatorsFor(key: string) {
  return constraintFor(key)?.operators ?? []
}

/** How many value inputs the chosen operator needs (0, 1 or 2). */
function arity(condition: QueryCondition): number {
  return operatorsFor(condition.key).find((o) => o.value === condition.operator)?.values ?? 1
}

function inputType(key: string): string {
  const type = constraintFor(key)?.type

  if (type === 'number') return 'number'
  if (type === 'date') return 'date'

  return 'text'
}

function emitWith(conditions: QueryCondition[]): void {
  emit('update:modelValue', conditions)
}

function add(): void {
  const first = props.constraints[0]
  if (!first) return

  emitWith([
    ...props.modelValue,
    { key: first.key, operator: first.operators[0]?.value ?? '', value: initialValue(first) },
  ])
}

/**
 * A fresh condition's value. Empty for everything the user types into, but a
 * boolean's select already shows "Yes" — leaving it empty would mean the chip
 * says one thing and the server, which drops valueless conditions, does
 * another.
 */
function initialValue(constraint: SchemaConstraint): string {
  return constraint.type === 'boolean' ? '1' : ''
}

function remove(index: number): void {
  emitWith(props.modelValue.filter((_, i) => i !== index))
}

function update(index: number, patch: Partial<QueryCondition>): void {
  emitWith(props.modelValue.map((condition, i) => (i === index ? { ...condition, ...patch } : condition)))
}

/**
 * Switching field resets the operator — the previous one usually is not valid
 * for the new type, and a stale operator is silently dropped server-side.
 */
function changeField(index: number, key: string): void {
  const constraint = constraintFor(key)

  update(index, {
    key,
    operator: operatorsFor(key)[0]?.value ?? '',
    value: constraint ? initialValue(constraint) : '',
    value2: '',
  })
}
</script>
