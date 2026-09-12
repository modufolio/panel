<template>
  <div class="ui-layout-block-quote border-l-2 border-ink pl-3">
    <textarea
      :value="text"
      rows="2"
      placeholder="Quote…"
      :disabled="disabled"
      aria-label="Quote text"
      class="block w-full resize-y border-0 bg-transparent p-0 text-lg leading-snug text-ink outline-none placeholder:text-ink-3 focus:ring-0"
      @input="emit('update', { text: ($event.target as HTMLTextAreaElement).value })"
    />
    <input
      :value="citation"
      type="text"
      placeholder="Citation…"
      :disabled="disabled"
      aria-label="Citation"
      class="mt-1 block w-full border-0 bg-transparent p-0 text-sm italic text-ink-3 outline-none placeholder:text-ink-3 focus:ring-0"
      @input="emit('update', { citation: ($event.target as HTMLInputElement).value })"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'

const props = defineProps({
  content: { type: Object as PropType<Record<string, unknown>>, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits<{
  (e: 'update', patch: Record<string, unknown>): void
}>()

const text = computed(() => (typeof props.content.text === 'string' ? props.content.text : ''))
const citation = computed(() => (typeof props.content.citation === 'string' ? props.content.citation : ''))
</script>
