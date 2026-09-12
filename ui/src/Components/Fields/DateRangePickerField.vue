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
        <label class="block text-xs font-medium text-label mb-1">
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
                ? 'border-danger focus:border-danger focus:ring-danger/20'
                : disabled
                ? 'border-line-strong bg-surface-sunken text-ink-3'
                : 'border-line-strong focus:border-primary focus:ring-primary/20',
              'pl-3 pr-10 py-2 text-sm',
            ]"
            @change="handleStartDateChange"
          />
          <InputIcon side="right">
            <svg
              class="h-5 w-5 text-ink-3"
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
          </InputIcon>
        </div>
      </div>

      <!-- End Date -->
      <div class="relative">
        <label class="block text-xs font-medium text-label mb-1">
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
                ? 'border-danger focus:border-danger focus:ring-danger/20'
                : disabled
                ? 'border-line-strong bg-surface-sunken text-ink-3'
                : 'border-line-strong focus:border-primary focus:ring-primary/20',
              'pl-3 pr-10 py-2 text-sm',
            ]"
            @change="handleEndDateChange"
          />
          <InputIcon side="right">
            <svg
              class="h-5 w-5 text-ink-3"
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
          </InputIcon>
        </div>
      </div>
    </div>

    <!-- Date Range Display -->
    <div v-if="startDate && endDate" class="text-xs text-ink-2">
      <span class="font-medium">{{ formatDateRange() }}</span>
      <span class="ml-2 text-ink-3">({{ getDayCount() }} days)</span>
    </div>

    <!-- Quick Presets -->
    <div v-if="showPresets && presets.length > 0" class="flex flex-wrap gap-2">
      <button
        v-for="preset in presets"
        :key="preset.label"
        type="button"
        class="rounded-md border border-line-strong px-3 py-1.5 text-xs font-medium transition-colors duration-200"
        :class="[
          isActivePreset(preset)
            ? 'border-primary bg-primary-surface text-primary-on-surface'
            : 'bg-surface text-label hover:bg-hover',
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
import { formatISO } from '../../Utils/dates'
import FieldPrimitive from './FieldPrimitive.vue'
import InputIcon from '../Core/InputIcon.vue'
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
  const todayStr = formatISO(today)

  if (preset.type === 'year') {
    startDate.value = formatISO(new Date(today.getFullYear(), 0, 1))
    endDate.value = todayStr
  } else if (preset.days === 0) {
    startDate.value = todayStr
    endDate.value = todayStr
  } else if (preset.days !== undefined) {
    const pastDate = new Date(today)
    pastDate.setDate(pastDate.getDate() - preset.days + 1)
    startDate.value = formatISO(pastDate)
    endDate.value = todayStr
  }

  updateValue()
}

function isActivePreset(preset: Preset) {
  const today = new Date()
  const todayStr = formatISO(today)

  if (preset.type === 'year') {
    return startDate.value === formatISO(new Date(today.getFullYear(), 0, 1)) && endDate.value === todayStr
  } else if (preset.days === 0) {
    return startDate.value === todayStr && endDate.value === todayStr
  } else {
    const pastDate = new Date(today)
    pastDate.setDate(pastDate.getDate() - (preset.days ?? 0) + 1)
    return startDate.value === formatISO(pastDate) && endDate.value === todayStr
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
