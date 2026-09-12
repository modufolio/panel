<template>
  <nav class="ui-record-nav flex items-center gap-2" :aria-label="ariaLabel">
    <component
      :is="side.url ? Link : 'span'"
      v-for="side in sides"
      :key="side.key"
      v-bind="side.url
        ? { href: side.url, title: `${side.label} (${side.hint})`, 'aria-label': side.label, rel: side.rel }
        : { 'aria-disabled': 'true', 'aria-label': `${side.label} (none)` }"
      class="inline-flex items-center justify-center w-9 h-9 rounded-lg border transition-colors"
      :class="side.url
        ? 'border-line-strong text-ink-2 hover:bg-hover'
        : 'border-line text-ink-3 cursor-not-allowed'"
      :data-testid="`record-nav-${side.key}`"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" :d="side.d" />
      </svg>
    </component>
  </nav>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'

/**
 * Previous/next through a list of records, for the header of a page that
 * edits one of them.
 *
 * The pair is always rendered: an absent neighbour is a dead button rather
 * than nothing, so the arrows keep their place and do not move under the
 * cursor as you step through records. Real links, not buttons, so the
 * ordinary browser gestures — middle-click, cmd-click, "open in new tab" —
 * keep working.
 */
const props = defineProps({
  /** The previous record's URL, or null at the start of the list. */
  previous: { type: String as () => string | null, default: null },
  /** The next record's URL, or null at the end of it. */
  next: { type: String as () => string | null, default: null },
  /**
   * What one record is called, for the tooltips and the screen reader —
   * "post", "page", "movie". The generic default suits a generated screen
   * that does not know what it is editing.
   */
  label: { type: String, default: 'record' },
  /** Set false where the page wants the arrow keys for something else. */
  keyboard: { type: Boolean, default: true },
})

const ariaLabel = computed(() => `${props.label}s`)

const sides = computed(() => [
  {
    key: 'previous',
    url: props.previous,
    rel: 'prev',
    hint: '←',
    label: `Previous ${props.label}`,
    d: 'M15 19l-7-7 7-7',
  },
  {
    key: 'next',
    url: props.next,
    rel: 'next',
    hint: '→',
    label: `Next ${props.label}`,
    d: 'M9 5l7 7-7 7',
  },
])

/**
 * Left/right step through the records.
 *
 * While a field has focus the arrows are the field's own — they move the
 * caret in a text input, the selection in a select, the handle on a slider —
 * so a keystroke aimed at one is left alone. So is any chord, which belongs
 * to the browser or the OS rather than to us.
 */
const onKeyDown = (event: KeyboardEvent): void => {
  const target = event.target as HTMLElement | null

  if (target && (['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A'].includes(target.tagName) || target.isContentEditable)) {
    return
  }

  if (event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) {
    return
  }

  const url = event.key === 'ArrowLeft' ? props.previous : event.key === 'ArrowRight' ? props.next : null

  if (url) {
    router.visit(url)
  }
}

onMounted(() => {
  if (props.keyboard) window.addEventListener('keydown', onKeyDown)
})

onUnmounted(() => window.removeEventListener('keydown', onKeyDown))
</script>
