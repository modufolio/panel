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

function applyPreset(preset: DatePreset) {
  const today = new Date()
  const todayStr = today.toISOString().split('T')[0]

  if (preset.type === 'month') {
    // This month
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1)
    start.value = monthStart.toISOString().split('T')[0]
    end.value = todayStr
  } else if (preset.type === 'last_month') {
    // Last month
    const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1)
    const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0)
    start.value = lastMonthStart.toISOString().split('T')[0]
    end.value = lastMonthEnd.toISOString().split('T')[0]
  } else if (preset.days === 0) {
    // Today
    start.value = todayStr
    end.value = todayStr
  } else {
    // Last X days
    const pastDate = new Date(today)
    pastDate.setDate(pastDate.getDate() - preset.days! + 1)
    start.value = pastDate.toISOString().split('T')[0]
    end.value = todayStr
  }

  emitUpdate()
}

function isActivePreset(preset: DatePreset) {
  const today = new Date()
  const todayStr = today.toISOString().split('T')[0]

  if (preset.type === 'month') {
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1)
    const monthStartStr = monthStart.toISOString().split('T')[0]
    return start.value === monthStartStr && end.value === todayStr
  } else if (preset.type === 'last_month') {
    const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1)
    const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0)
    return (
      start.value === lastMonthStart.toISOString().split('T')[0] &&
      end.value === lastMonthEnd.toISOString().split('T')[0]
    )
  } else if (preset.days === 0) {
    return start.value === todayStr && end.value === todayStr
  } else {
    const pastDate = new Date(today)
    pastDate.setDate(pastDate.getDate() - preset.days! + 1)
    const pastDateStr = pastDate.toISOString().split('T')[0]
    return start.value === pastDateStr && end.value === todayStr
  }
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
