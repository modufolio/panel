<template>
  <div class="ui-layout-block-heading flex items-center gap-2" :data-level="level">
    <input
      :value="text"
      type="text"
      placeholder="Heading…"
      :disabled="disabled"
      aria-label="Heading text"
      class="min-w-0 flex-1 border-0 bg-transparent p-0 font-semibold text-ink outline-none placeholder:text-ink-3 focus:ring-0"
      :class="sizeClass"
      @input="emit('update', { text: ($event.target as HTMLInputElement).value })"
    />
    <select
      :value="level"
      :disabled="disabled"
      aria-label="Heading level"
      class="shrink-0 cursor-pointer border-0 bg-transparent py-0 pl-1 pr-6 text-xs font-semibold uppercase text-ink-3 focus:ring-0"
      @change="emit('update', { level: ($event.target as HTMLSelectElement).value })"
    >
      <option v-for="l in HEADING_LEVELS" :key="l" :value="l">{{ l.toUpperCase() }}</option>
    </select>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import { HEADING_LEVELS } from '../layoutModel'

const props = defineProps({
  content: { type: Object as PropType<Record<string, unknown>>, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits<{
  (e: 'update', patch: Record<string, unknown>): void
}>()

const text = computed(() => (typeof props.content.text === 'string' ? props.content.text : ''))

const level = computed(() => {
  const l = props.content.level
  return typeof l === 'string' && (HEADING_LEVELS as readonly string[]).includes(l) ? l : 'h2'
})

const sizeClass = computed(() => ({
  h1: 'text-2xl',
  h2: 'text-xl',
  h3: 'text-lg',
  h4: 'text-base',
  h5: 'text-sm',
  h6: 'text-xs uppercase tracking-wide',
})[level.value])
</script>
