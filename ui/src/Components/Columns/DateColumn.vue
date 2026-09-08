<template>
  <div class="ui-date-column">
    <span :class="labelClass">{{ formattedDate }}</span>
    <span v-if="description" class="block text-xs text-gray-500 mt-0.5">
      {{ description }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
// One definition, shared with the drawer's field grid: the same moment must
// not read one way in a cell and another in a drawer.
import { formatDate, relativeTime } from '../../Utils/dates'

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
  if (!props.value) return '—'

  try {
    const date = new Date(props.value)

    if (isNaN(date.getTime())) {
      return '—'
    }

    // Relative time (e.g., "2 hours ago")
    if (props.relative) {
      return relativeTime(date)
    }

    // Format based on props.format
    return formatDate(date, props.format)
  } catch {
    return '—'
  }
})
</script>
