<template>
  <table
    class="ui-month-grid w-full border-collapse select-none"
    role="grid"
    :aria-label="`${monthName} ${year}`"
  >
    <thead aria-hidden="true">
      <tr>
        <th
          v-for="weekday in weekdayLabels"
          :key="weekday"
          class="pb-1 text-center text-xs font-medium text-gray-400"
          scope="col"
        >{{ weekday }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="(week, w) in weeks" :key="w" role="row">
        <td
          v-for="(date, d) in week"
          :key="d"
          role="gridcell"
          class="p-0 text-center"
          :aria-selected="date !== null && isSelected(date) ? 'true' : undefined"
          :aria-disabled="date !== null && !allowed(date) ? 'true' : undefined"
        >
          <button
            v-if="date !== null"
            :ref="(el) => setCellRef(date, el)"
            type="button"
            class="mx-auto flex h-8 w-8 items-center justify-center rounded-full text-sm transition-colors"
            :class="cellClass(date)"
            :tabindex="isFocused(date) ? 0 : -1"
            :aria-label="cellLabel(date)"
            @click="onTap(date)"
            @keydown="$emit('cell-keydown', $event)"
            @focus="$emit('cell-focus', date)"
          >{{ date.getDate() }}</button>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script setup lang="ts">
import { computed, type ComponentPublicInstance, type PropType } from 'vue'
import { dateAllowed, dateEquals, monthMatrix, todayMidnight } from '../../../Utils/dates'

const props = defineProps({
  /** 0-based month of the grid being shown. */
  month: { type: Number, required: true },
  year: { type: Number, required: true },
  selectedDate: { type: Date as PropType<Date | null>, default: null },
  focusedDate: { type: Date as PropType<Date | null>, default: null },
  min: { type: Date as PropType<Date | null>, default: null },
  max: { type: Date as PropType<Date | null>, default: null },
  /** 0 = Sunday, 1 = Monday. */
  firstDayOfWeek: { type: Number, default: 1 },
})

const emit = defineEmits<{
  'date-tap': [date: Date]
  'cell-keydown': [event: KeyboardEvent]
  'cell-focus': [date: Date]
}>()

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

const weeks = computed(() => monthMatrix(props.year, props.month, props.firstDayOfWeek))
const monthName = computed(() => MONTHS[props.month])
const weekdayLabels = computed(() =>
  Array.from({ length: 7 }, (_, i) => WEEKDAYS[(props.firstDayOfWeek + i) % 7])
)

const isSelected = (date: Date) => dateEquals(date, props.selectedDate)
const isFocused = (date: Date) => dateEquals(date, props.focusedDate)
const isToday = (date: Date) => dateEquals(date, todayMidnight())
const allowed = (date: Date) => dateAllowed(date, props.min, props.max)

const cellClass = (date: Date) => {
  if (isSelected(date)) {
    return 'bg-primary-600 font-semibold text-white'
  }
  if (!allowed(date)) {
    return 'cursor-default text-gray-300'
  }
  if (isToday(date)) {
    return 'font-semibold text-primary-600 hover:bg-primary-50'
  }
  return 'text-gray-700 hover:bg-gray-100'
}

const cellLabel = (date: Date) => {
  const weekday = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][date.getDay()]
  const base = `${date.getDate()} ${monthName.value} ${props.year}, ${weekday}`
  return isToday(date) ? `${base}, Today` : base
}

// Disabled dates stay focusable (only selection is blocked), per ARIA grid
// practice — so the ref map covers every cell, and tap filters instead.
const cellRefs = new Map<number, HTMLButtonElement>()

const setCellRef = (date: Date, el: Element | ComponentPublicInstance | null) => {
  if (el instanceof HTMLButtonElement) {
    cellRefs.set(date.getTime(), el)
  } else {
    cellRefs.delete(date.getTime())
  }
}

const onTap = (date: Date) => {
  if (allowed(date)) {
    emit('date-tap', date)
  }
}

defineExpose({
  focusDate(date: Date): boolean {
    const cell = cellRefs.get(date.getTime())
    cell?.focus()
    return cell !== undefined
  },
})
</script>
