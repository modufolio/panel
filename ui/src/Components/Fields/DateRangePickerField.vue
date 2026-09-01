<template>
  <FieldPrimitive
    v-bind="{ width, label, help, error, required }"
    wrapper-class="ui-field-date-range space-y-1.5 border-0 p-0 m-0"
    as="fieldset"
    label-spacing="none"
  >
    <div class="grid grid-cols-2 gap-3">
      <!-- Start Date -->
      <div class="relative">
        <label class="block text-xs font-medium text-gray-600 mb-1">
          Start Date
        </label>
        <div class="relative">
          <input
            v-model="startDate"
            type="date"
            :min="min"
            :max="endDate || max"
            :disabled="disabled"
            class="ui-input block w-full"
            :class="[
              error
                ? 'border-danger-600 focus:border-danger-600 focus:ring-danger-600/20'
                : disabled
                ? 'border-gray-300 bg-gray-50 text-gray-500'
                : 'border-gray-300 focus:border-primary-600 focus:ring-primary-600/20',
              'pl-3 pr-10 py-2 text-sm',
            ]"
            @change="handleStartDateChange"
          />
          <div
            class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
          >
            <svg
              class="h-5 w-5 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
              />
            </svg>
          </div>
        </div>
      </div>

      <!-- End Date -->
      <div class="relative">
        <label class="block text-xs font-medium text-gray-600 mb-1">
          End Date
        </label>
        <div class="relative">
          <input
            v-model="endDate"
            type="date"
            :min="startDate || min"
            :max="max"
            :disabled="disabled"
            class="ui-input block w-full"
            :class="[
              error
                ? 'border-danger-600 focus:border-danger-600 focus:ring-danger-600/20'
                : disabled
                ? 'border-gray-300 bg-gray-50 text-gray-500'
                : 'border-gray-300 focus:border-primary-600 focus:ring-primary-600/20',
              'pl-3 pr-10 py-2 text-sm',
            ]"
            @change="handleEndDateChange"
          />
          <div
            class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
          >
            <svg
              class="h-5 w-5 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
              />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Date Range Display -->
    <div v-if="startDate && endDate" class="text-xs text-gray-600">
      <span class="font-medium">{{ formatDateRange() }}</span>
      <span class="ml-2 text-gray-500">({{ getDayCount() }} days)</span>
    </div>

    <!-- Quick Presets -->
    <div v-if="showPresets && presets.length > 0" class="flex flex-wrap gap-2">
      <button
        v-for="preset in presets"
        :key="preset.label"
        type="button"
        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium transition-colors duration-200"
        :class="[
          isActivePreset(preset)
            ? 'border-primary-600 bg-primary-50 text-primary-700'
            : 'bg-white text-gray-700 hover:bg-gray-50',
          disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
        ]"
        :disabled="disabled"
        @click="selectPreset(preset)"
      >
        {{ preset.label }}
      </button>
    </div>
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'

interface Preset {
  label: string
  days?: number
  type?: string
}

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: Object,
    default: () => ({ start: null, end: null }),
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
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  min: {
    type: String,
    default: '',
  },
  max: {
    type: String,
    default: '',
  },
  showPresets: {
    type: Boolean,
    default: true,
  },
  presets: {
    type: Array as () => Preset[],
    default: () => [
      { label: 'Today', days: 0 },
      { label: 'Last 7 days', days: 7 },
      { label: 'Last 30 days', days: 30 },
      { label: 'Last 90 days', days: 90 },
      { label: 'This year', type: 'year' },
    ],
  },
})

const emit = defineEmits(['update:modelValue'])


// State
const startDate = ref(props.modelValue?.start || '')
const endDate = ref(props.modelValue?.end || '')

// Functions
function handleStartDateChange() {
  updateValue()
}

function handleEndDateChange() {
  updateValue()
}

function updateValue() {
  const value = {
    start: startDate.value || null,
    end: endDate.value || null,
  }
  emit('update:modelValue', value)
}

function formatDateRange() {
  if (!startDate.value || !endDate.value) return ''

  const start = new Date(startDate.value)
  const end = new Date(endDate.value)

  const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  }

  return `${formatDate(start)} - ${formatDate(end)}`
}

function getDayCount() {
  if (!startDate.value || !endDate.value) return 0

  const start = new Date(startDate.value)
  const end = new Date(endDate.value)
  const diffTime = Math.abs(end.getTime() - start.getTime())
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  return diffDays + 1 // Include both start and end dates
}

function selectPreset(preset: Preset) {
  if (props.disabled) return

  const today = new Date()
  const todayStr = today.toISOString().split('T')[0]

  if (preset.type === 'year') {
    // This year
    const yearStart = new Date(today.getFullYear(), 0, 1)
    startDate.value = yearStart.toISOString().split('T')[0]
    endDate.value = todayStr
  } else if (preset.days === 0) {
    // Today
    startDate.value = todayStr
    endDate.value = todayStr
  } else if (preset.days !== undefined) {
    // Last X days
    const pastDate = new Date(today)
    pastDate.setDate(pastDate.getDate() - preset.days + 1)
    startDate.value = pastDate.toISOString().split('T')[0]
    endDate.value = todayStr
  }

  updateValue()
}

function isActivePreset(preset: Preset) {
  const today = new Date()
  const todayStr = today.toISOString().split('T')[0]

  if (preset.type === 'year') {
    const yearStart = new Date(today.getFullYear(), 0, 1)
    const yearStartStr = yearStart.toISOString().split('T')[0]
    return startDate.value === yearStartStr && endDate.value === todayStr
  } else if (preset.days === 0) {
    return startDate.value === todayStr && endDate.value === todayStr
  } else {
    const pastDate = new Date(today)
    pastDate.setDate(pastDate.getDate() - (preset.days ?? 0) + 1)
    const pastDateStr = pastDate.toISOString().split('T')[0]
    return startDate.value === pastDateStr && endDate.value === todayStr
  }
}

// Watch for external changes
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      startDate.value = newValue.start || ''
      endDate.value = newValue.end || ''
    }
  },
  { deep: true }
)
</script>
