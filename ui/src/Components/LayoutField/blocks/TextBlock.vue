<template>
  <div class="ui-layout-block-text">
    <ProseMirrorBuilderField
      :model-value="doc"
      label=""
      width="full"
      :insert-bar="false"
      @update:model-value="emit('update', { text: $event })"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import { DOMParser as PMDOMParser } from 'prosemirror-model'
import ProseMirrorBuilderField from '../../Fields/ProseMirrorBuilderField.vue'
import { schema } from '../../../Builder/schema'

const props = defineProps({
  content: { type: Object as PropType<Record<string, unknown>>, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits<{
  (e: 'update', patch: Record<string, unknown>): void
}>()

const doc = computed(() => {
  const text = props.content.text
  if (typeof text !== 'string' || text.trim() === '') return ''
  if (isDocJson(text)) return text
  return htmlToDoc(text)
})

function isDocJson(value: string): boolean {
  if (!value.startsWith('{')) return false
  try {
    const parsed = JSON.parse(value) as { type?: unknown }
    return parsed?.type === 'doc'
  } catch {
    return false
  }
}

function htmlToDoc(html: string): string {
  if (typeof DOMParser === 'undefined') return ''
  // An inert document: nothing in it loads or runs, unlike innerHTML on a
  // detached element, where an <img onerror> still fires.
  const parsed = new DOMParser().parseFromString(html, 'text/html')
  const node = PMDOMParser.fromSchema(schema).parse(parsed.body)
  return JSON.stringify(node.toJSON())
}
</script>
