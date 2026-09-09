<template>
  <div class="space-y-3 p-4">
    <label class="block text-sm font-medium text-gray-900">
      {{ label }}
    </label>

    <div class="grid grid-cols-2 gap-2">
      <!-- Start Date -->
      <div>
        <label class="block text-xs text-gray-600 mb-1">From</label>
        <input
          v-model="start"
          type="date"
          :max="end || max"
          :min="min"
          class="ui-input block w-full"
          @change="emitUpdate"
        />
      </div>

      <!-- End Date -->
      <div>
        <label class="block text-xs text-gray-600 mb-1">To</label>
        <input
          v-model="end"
          type="date"
          :min="start || min"
          :max="max"
          class="ui-input block w-full"
          @change="emitUpdate"
        />
      </div>
    </div>

    <!-- Presets -->
    <div v-if="showPresets" class="flex flex-wrap gap-1.5">
      <button
        v-for="preset in presets"
        :key="preset.label"
        type="button"
        class="rounded-md border px-2 py-1 text-xs transition-colors"
        :class="[
          isActivePreset(preset)
            ? 'border-primary-600 bg-primary-50 text-primary-700 font-medium'
            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
        ]"
        @click="applyPreset(preset)"
      >
        {{ preset.label }}
      </button>
    </div>

    <!-- Clear button -->
    <button
      v-if="start || end"
      type="button"
      class="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
      @click="clear"
    >
      Clear Filter
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, type PropType } from 'vue'

interface DatePreset {
  label: string
  days?: number
  type?: string
}

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ start: null, end: null }),
  },
  label: {
    type: String,
    default: 'Date Range',
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
    type: Array as PropType<DatePreset[]>,
    default: () => [
      { label: 'Today', days: 0 },
      { label: 'Last 7 days', days: 7 },
      { label: 'Last 30 days', days: 30 },
      { label: 'This month', type: 'month' },
      { label: 'Last month', type: 'last_month' },
    ],
  },
})

const emit = defineEmits(['update:modelValue'])

const start = ref(props.modelValue?.start || '')
const end = ref(props.modelValue?.end || '')

function emitUpdate() {
  emit('update:modelValue', {
    start: start.value || null,
    end: end.value || null,
  })
}

function clear() {
  start.value = ''
  end.value = ''
  emitUpdate()
}

/**
 * A calendar day as `YYYY-MM-DD`, read in the viewer's own timezone.
 *
 * Not `toISOString()`: that formats in UTC, and the month presets build their
 * dates at local midnight — so anywhere east of UTC "This month" began on the
 * last day of the previous one, and "Today" flipped to yesterday for anyone
 * filtering before their offset had elapsed.
 */
function isoDate(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

/**
 * The range a preset stands for, today. One definition rather than two:
 * applying a preset and recognising the one in force used to compute the same
 * four dates separately, which is two places for a boundary to drift.
 */
function rangeFor(preset: DatePreset): { start: string; end: string } {
  const today = new Date()
  const todayStr = isoDate(today)

  if (preset.type === 'month') {
    return { start: isoDate(new Date(today.getFullYear(), today.getMonth(), 1)), end: todayStr }
  }

  if (preset.type === 'last_month') {
    // Day 0 of this month is the last day of the previous one.
    return {
      start: isoDate(new Date(today.getFullYear(), today.getMonth() - 1, 1)),
      end: isoDate(new Date(today.getFullYear(), today.getMonth(), 0)),
    }
  }

  if (preset.days === 0) {
    return { start: todayStr, end: todayStr }
  }

  // Today counts as one of the N, so "Last 7 days" reaches back six.
  const from = new Date(today)
  from.setDate(from.getDate() - preset.days! + 1)

  return { start: isoDate(from), end: todayStr }
}

function applyPreset(preset: DatePreset) {
  const range = rangeFor(preset)

  start.value = range.start
  end.value = range.end

  emitUpdate()
}

function isActivePreset(preset: DatePreset) {
  const range = rangeFor(preset)

  return start.value === range.start && end.value === range.end
}

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      start.value = newValue.start || ''
      end.value = newValue.end || ''
    }
  },
  { deep: true }
)
</script>
