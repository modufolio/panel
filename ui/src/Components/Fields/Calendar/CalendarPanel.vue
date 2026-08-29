<template>
  <div class="ui-calendar w-72 rounded-lg bg-white p-3 shadow-lg ring-1 ring-black/5" role="dialog" aria-label="Choose date">
    <div class="mb-2 flex items-center justify-between">
      <button
        type="button"
        class="flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-700"
        aria-label="Previous month"
        @click="moveViewBy(-1)"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
      </button>

      <span class="text-sm font-semibold text-gray-800" aria-live="polite">{{ title }}</span>

      <button
        type="button"
        class="flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-700"
        aria-label="Next month"
        @click="moveViewBy(1)"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
      </button>
    </div>

    <MonthGrid
      ref="grid"
      :month="viewMonth"
      :year="viewYear"
      :selected-date="selectedDate"
      :focused-date="focusedDate"
      :min="min"
      :max="max"
      :first-day-of-week="firstDayOfWeek"
      @date-tap="$emit('select', $event)"
      @cell-keydown="onCellKeydown"
      @cell-focus="onCellFocus"
    />

    <div class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2">
      <button
        type="button"
        class="rounded px-2 py-1 text-sm text-primary-600 hover:bg-primary-50 disabled:cursor-default disabled:text-gray-300 disabled:hover:bg-transparent"
        :disabled="!todayAllowed"
        @click="onTodayClick"
      >Today</button>

      <button
        type="button"
        class="rounded px-2 py-1 text-sm text-gray-500 hover:bg-gray-100"
        @click="$emit('close')"
      >Cancel</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch, type PropType } from 'vue'
import MonthGrid from './MonthGrid.vue'
import {
  addDays, addMonths, clampToRange, dateAllowed, dateEquals,
  daysInMonth, makeDate, todayMidnight,
} from '../../../Utils/dates'

const props = defineProps({
  selectedDate: { type: Date as PropType<Date | null>, default: null },
  focusedDate: { type: Date as PropType<Date | null>, default: null },
  min: { type: Date as PropType<Date | null>, default: null },
  max: { type: Date as PropType<Date | null>, default: null },
  firstDayOfWeek: { type: Number, default: 1 },
})

const emit = defineEmits<{
  'select': [date: Date]
  'update:focusedDate': [date: Date]
  'close': []
}>()

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

const grid = ref<InstanceType<typeof MonthGrid> | null>(null)

// The shown month follows the focused date; falls back to selection or today.
const viewDate = computed(() => props.focusedDate ?? props.selectedDate ?? todayMidnight())
const viewMonth = computed(() => viewDate.value.getMonth())
const viewYear = computed(() => viewDate.value.getFullYear())
const title = computed(() => `${MONTHS[viewMonth.value]} ${viewYear.value}`)

const todayAllowed = computed(() => dateAllowed(todayMidnight(), props.min, props.max))

// Day-of-month the user last chose explicitly. Month/year jumps clamp to
// short months but keep aiming for this day: Jan 31 → Feb 28 → Mar 31.
let anchorDay: number | null = null

const moveFocus = (date: Date) => {
  emit('update:focusedDate', clampToRange(date, props.min, props.max))
}

const moveViewBy = (months: number) => {
  moveFocus(addMonths(viewDate.value, months, anchorDay ?? undefined))
}

const onTodayClick = () => {
  const today = todayMidnight()

  // First press navigates to today; a second press, when already focused
  // there, selects it.
  if (dateEquals(props.focusedDate, today)) {
    emit('select', today)
  } else {
    anchorDay = null
    moveFocus(today)
    void focusActiveCell()
  }
}

const onCellFocus = (date: Date) => {
  if (!dateEquals(date, props.focusedDate)) {
    emit('update:focusedDate', date)
  }
}

const onCellKeydown = (event: KeyboardEvent) => {
  const current = props.focusedDate ?? viewDate.value

  let next: Date | null = null
  switch (event.key) {
    case 'ArrowLeft': next = addDays(current, -1); break
    case 'ArrowRight': next = addDays(current, 1); break
    case 'ArrowUp': next = addDays(current, -7); break
    case 'ArrowDown': next = addDays(current, 7); break
    case 'PageUp': next = addMonths(current, event.shiftKey ? -12 : -1, anchorDay ?? undefined); break
    case 'PageDown': next = addMonths(current, event.shiftKey ? 12 : 1, anchorDay ?? undefined); break
    case 'Home': next = makeDate(current.getFullYear(), current.getMonth(), 1); break
    case 'End': next = makeDate(current.getFullYear(), current.getMonth(), daysInMonth(current.getFullYear(), current.getMonth())); break
    case 'Enter':
    case ' ':
      event.preventDefault()
      if (dateAllowed(current, props.min, props.max)) {
        emit('select', current)
      }
      return
    case 'Escape':
      emit('close')
      return
    default:
      return
  }

  event.preventDefault()

  // Arrow/Home/End picks a concrete day — that day becomes the new anchor.
  // Month and year jumps keep the previous anchor.
  if (!event.key.startsWith('Page')) {
    anchorDay = next.getDate()
  } else if (anchorDay === null) {
    anchorDay = current.getDate()
  }

  moveFocus(next)
  void focusActiveCell()
}

const focusActiveCell = async () => {
  await nextTick()
  const target = props.focusedDate ?? viewDate.value
  grid.value?.focusDate(target)
}

// Selecting or reopening resets the remembered anchor.
watch(() => props.selectedDate, () => {
  anchorDay = null
})

defineExpose({
  /** Move DOM focus into the grid, on the focused (or fallback) date. */
  focusGrid: () => focusActiveCell(),
})
</script>
