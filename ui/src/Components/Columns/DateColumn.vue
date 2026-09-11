<template>
  <div class="ui-date-column">
    <span :class="labelClass">{{ formattedDate }}</span>
    <span v-if="description" class="block text-xs text-ink-3 mt-0.5">
      {{ description }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
// One definition, shared with the drawer's field grid: the same moment must
// not read one way in a cell and another in a drawer.
import { date, fromUnix, type DateValue } from '../../Utils/dates'

const props = defineProps({
  value: {
    type: [String, Date, Number],
    default: null,
  },
  format: {
    type: String,
    default: 'MMM D, YYYY', // Default format
  },
  description: {
    type: String,
    default: '',
  },
  labelClass: {
    type: String,
    default: '',
  },
  timezone: {
    type: String,
    default: null,
  },
  relative: {
    type: Boolean,
    default: false,
  },
})

const formattedDate = computed(() => {
  // A number is milliseconds here, as `new Date(n)` has always read it in this
  // column; fromUnix() is what the seconds-counting server side would use.
  const value: DateValue | null = typeof props.value === 'number'
    ? fromUnix(props.value / 1000)
    : date(props.value)

  if (value === null) {
    return '—'
  }

  // Relative time (e.g., "2 hours ago")
  return props.relative ? value.relative() : value.format(props.format)
})
</script>
