<template>
  <div class="space-y-3 p-4">
    <label class="block text-sm font-medium text-gray-900">
      {{ label }}
    </label>

    <!-- Operator Selection -->
    <select
      v-model="operator"
      class="ui-input block w-full"
      @change="emitUpdate"
    >
      <option value="=">=  (Equals)</option>
      <option value="!=">≠  (Not Equals)</option>
      <option value=">">&gt;  (Greater Than)</option>
      <option value=">=">&ge;  (Greater or Equal)</option>
      <option value="<">&lt;  (Less Than)</option>
      <option value="<=">&le;  (Less or Equal)</option>
      <option value="between">Between</option>
    </select>

    <!-- Single Value Input -->
    <div v-if="operator !== 'between'">
      <input
        v-model.number="value"
        type="number"
        :placeholder="`Enter ${label.toLowerCase()}`"
        :step="step"
        :min="min"
        :max="max"
        class="ui-input block w-full"
        @input="emitUpdate"
      />
    </div>

    <!-- Range Inputs (for "between") -->
    <div v-else class="grid grid-cols-2 gap-2">
      <div>
        <label class="block text-xs text-gray-600 mb-1">Min</label>
        <input
          v-model.number="rangeMin"
          type="number"
          placeholder="Min"
          :step="step"
          :min="min"
          :max="rangeMax || max"
          class="ui-input block w-full"
          @input="emitUpdate"
        />
      </div>
      <div>
        <label class="block text-xs text-gray-600 mb-1">Max</label>
        <input
          v-model.number="rangeMax"
          type="number"
          placeholder="Max"
          :step="step"
          :min="rangeMin || min"
          :max="max"
          class="ui-input block w-full"
          @input="emitUpdate"
        />
      </div>
    </div>

    <!-- Clear button -->
    <button
      v-if="hasValue"
      type="button"
      class="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
      @click="clear"
    >
      Clear Filter
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ operator: '=', value: null }),
  },
  label: {
    type: String,
    default: 'Number',
  },
  min: {
    type: Number,
    default: undefined,
  },
  max: {
    type: Number,
    default: undefined,
  },
  step: {
    type: Number,
    default: 1,
  },
})

const emit = defineEmits(['update:modelValue'])

const operator = ref(props.modelValue?.operator || '=')
const value = ref(props.modelValue?.value || null)
const rangeMin = ref(props.modelValue?.rangeMin || null)
const rangeMax = ref(props.modelValue?.rangeMax || null)

const hasValue = computed(() => {
  if (operator.value === 'between') {
    return rangeMin.value !== null || rangeMax.value !== null
  }
  return value.value !== null
})

function emitUpdate() {
  if (operator.value === 'between') {
    emit('update:modelValue', {
      operator: operator.value,
      rangeMin: rangeMin.value,
      rangeMax: rangeMax.value,
    })
  } else {
    emit('update:modelValue', {
      operator: operator.value,
      value: value.value,
    })
  }
}

function clear() {
  operator.value = '='
  value.value = null
  rangeMin.value = null
  rangeMax.value = null
  emitUpdate()
}

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      operator.value = newValue.operator || '='
      value.value = newValue.value !== undefined ? newValue.value : null
      rangeMin.value = newValue.rangeMin !== undefined ? newValue.rangeMin : null
      rangeMax.value = newValue.rangeMax !== undefined ? newValue.rangeMax : null
    }
  },
  { deep: true }
)
</script>
