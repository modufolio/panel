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
      return getRelativeTime(date)
    }

    // Format based on props.format
    return formatDate(date, props.format)
  } catch {
    return '—'
  }
})

function formatDate(date: Date, format: string) {
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const fullMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

  const d = date.getDate()
  const m = date.getMonth()
  const y = date.getFullYear()
  const h = date.getHours()
  const min = date.getMinutes()
  const s = date.getSeconds()

  const tokens: Record<string, string | number> = {
    'YYYY': y,
    'YY': String(y).slice(-2),
    'MMMM': fullMonths[m],
    'MMM': months[m],
    'MM': String(m + 1).padStart(2, '0'),
    'M': m + 1,
    'DD': String(d).padStart(2, '0'),
    'D': d,
    'HH': String(h).padStart(2, '0'),
    'H': h,
    'hh': String(h % 12 || 12).padStart(2, '0'),
    'h': h % 12 || 12,
    'mm': String(min).padStart(2, '0'),
    'm': min,
    'ss': String(s).padStart(2, '0'),
    's': s,
    'A': h >= 12 ? 'PM' : 'AM',
    'a': h >= 12 ? 'pm' : 'am',
  }

  // Single pass, longest-token-first alternation.
  //
  // The previous implementation swapped each token for a `__PLACEHOLDER_n__`
  // marker and substituted afterwards — but the literal word "PLACEHOLDER"
  // contains D, H and A, so the single-character tokens matched *inside*
  // markers already written and shredded them. Even the default
  // 'MMM D, YYYY' came out mangled. Replacing in one pass means no output is
  // ever rescanned.
  const pattern = /YYYY|YY|MMMM|MMM|MM|M|DD|D|HH|H|hh|h|mm|m|ss|s|A|a/g

  return format.replace(pattern, (token: string) => String(tokens[token]))
}

function getRelativeTime(date: Date) {
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffSec = Math.floor(diffMs / 1000)
  const diffMin = Math.floor(diffSec / 60)
  const diffHour = Math.floor(diffMin / 60)
  const diffDay = Math.floor(diffHour / 24)
  const diffWeek = Math.floor(diffDay / 7)
  const diffMonth = Math.floor(diffDay / 30)
  const diffYear = Math.floor(diffDay / 365)

  if (diffSec < 60) return 'just now'
  if (diffMin < 60) return `${diffMin} ${diffMin === 1 ? 'minute' : 'minutes'} ago`
  if (diffHour < 24) return `${diffHour} ${diffHour === 1 ? 'hour' : 'hours'} ago`
  if (diffDay < 7) return `${diffDay} ${diffDay === 1 ? 'day' : 'days'} ago`
  if (diffWeek < 4) return `${diffWeek} ${diffWeek === 1 ? 'week' : 'weeks'} ago`
  if (diffMonth < 12) return `${diffMonth} ${diffMonth === 1 ? 'month' : 'months'} ago`
  return `${diffYear} ${diffYear === 1 ? 'year' : 'years'} ago`
}
</script>
