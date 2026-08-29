<template>
  <div class="space-y-3 p-4">
    <label class="block text-sm font-medium text-gray-900">
      {{ label }}
    </label>

    <!-- Search -->
    <div v-if="searchable" class="relative">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search..."
        class="ui-input block w-full"
      />
    </div>

    <!-- Options List -->
    <div class="max-h-64 space-y-1 overflow-y-auto">
      <label
        v-for="option in filteredOptions"
        :key="option.value"
        class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 hover:bg-gray-50"
      >
        <input
          type="checkbox"
          :checked="isSelected(option.value)"
          @change="toggleOption(option.value)"
          class="rounded border-gray-300 text-primary-600 focus:ring-primary-600"
        />
        <span class="flex-1 text-sm text-gray-700">{{ option.label }}</span>
        <span
          v-if="option.count !== undefined"
          class="text-xs text-gray-500"
        >
          {{ option.count }}
        </span>
      </label>

      <!-- No Results -->
      <div
        v-if="filteredOptions.length === 0"
        class="py-4 text-center text-sm text-gray-500"
      >
        {{ searchQuery ? 'No results found' : 'No options available' }}
      </div>
    </div>

    <!-- Select All / Clear All -->
    <div class="flex items-center justify-between border-t border-gray-200 pt-2">
      <button
        v-if="selectedValues.length < normalizedOptions.length"
        type="button"
        class="text-xs font-medium text-primary-600 hover:text-primary-700"
        @click="selectAll"
      >
        Select All
      </button>
      <button
        v-if="selectedValues.length > 0"
        type="button"
        class="text-xs font-medium text-gray-600 hover:text-gray-700"
        @click="clear"
      >
        Clear ({{ selectedValues.length }})
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, type PropType } from 'vue'

interface FilterOption {
  value: any
  label: string
  count?: number
}

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  label: {
    type: String,
    default: 'Select Options',
  },
  options: {
    type: Array as PropType<(FilterOption | string)[]>,
    required: true,
  },
  searchable: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const selectedValues = ref([...props.modelValue])
const searchQuery = ref('')

// Normalize options to { label, value, count? } format
const normalizedOptions = computed(() => {
  return props.options.map((option) => {
    if (typeof option === 'string') {
      return { label: option, value: option }
    }
    return option
  })
})

// Filter options based on search query
const filteredOptions = computed(() => {
  if (!props.searchable || !searchQuery.value) {
    return normalizedOptions.value
  }

  const query = searchQuery.value.toLowerCase()
  return normalizedOptions.value.filter((option) =>
    option.label.toLowerCase().includes(query)
  )
})

function isSelected(value: any) {
  return selectedValues.value.includes(value)
}

function toggleOption(value: any) {
  const index = selectedValues.value.indexOf(value)
  if (index > -1) {
    selectedValues.value.splice(index, 1)
  } else {
    selectedValues.value.push(value)
  }
  emit('update:modelValue', selectedValues.value)
}

function selectAll() {
  selectedValues.value = normalizedOptions.value.map((opt) => opt.value)
  emit('update:modelValue', selectedValues.value)
}

function clear() {
  selectedValues.value = []
  emit('update:modelValue', [])
}

watch(
  () => props.modelValue,
  (newValue) => {
    selectedValues.value = [...newValue]
  },
  { deep: true }
)
</script>
