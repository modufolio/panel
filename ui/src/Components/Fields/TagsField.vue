<template>
  <FieldPrimitive
    v-bind="{ width, id, label, help, error, required }"
    wrapper-class="ui-field-tags"
    v-slot="{ describedBy, invalid }"
  >
    <!-- Clicking anywhere in the box focuses the input, so the whole control
         behaves like a text field rather than a row of buttons. -->
    <div
      class="ui-field-wrapper flex flex-wrap items-center gap-1.5 rounded-xs border bg-white px-2 py-1.5 shadow-sm transition-colors"
      :class="[
        error
          ? 'border-danger-500 focus-within:border-danger-600 focus-within:ring-2 focus-within:ring-danger-600/20'
          : 'border-gray-300 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/20',
        disabled && 'bg-gray-50 cursor-not-allowed',
      ]"
      @click="focusInput"
    >
      <span
        v-for="(tag, i) in tags"
        :key="`${tag}-${i}`"
        class="inline-flex items-center gap-1 rounded-md bg-gray-100 py-0.5 pl-2 pr-1 text-sm text-gray-700 ring-1 ring-inset ring-gray-200"
      >
        {{ tag }}
        <button
          type="button"
          class="rounded text-gray-500 hover:text-danger-600 hover:bg-gray-200 transition-colors"
          :aria-label="`Remove ${tag}`"
          :disabled="disabled"
          @click.stop="removeAt(i)"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </span>

      <input
        :id="id"
        ref="inputEl"
        v-model="draft"
        type="text"
        class="ui-field-input flex-1 min-w-32 border-0 p-0.5 text-sm placeholder-gray-400 focus:ring-0 focus:outline-none disabled:bg-transparent"
        :placeholder="tags.length ? '' : placeholder"
        :disabled="disabled"
        :aria-describedby="describedBy"
        :aria-invalid="invalid"
        @keydown.enter.prevent="commitDraft"
        @keydown="onKeydown"
        @blur="commitDraft"
        @paste="onPaste"
      />
    </div>
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useId } from '../../Primitives/useId'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'

/**
 * Free-form tag input backed by a comma-separated string, matching how the
 * flat files store the value (`Tags: island, italy, luxury, sun`). The model
 * stays a plain string so nothing downstream has to change.
 */
const props = defineProps({
  ...fieldWidthProp,
  modelValue: { type: String, default: '' },
  id: { type: String, default: () => useId(undefined, 'field') },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  error: { type: String, default: '' },
  placeholder: { type: String, default: 'Add a tag and press Enter…' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  separator: { type: String, default: ', ' },
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()


const inputEl = ref<HTMLInputElement | null>(null)
const draft = ref('')

const tags = computed(() =>
  props.modelValue.split(',').map(t => t.trim()).filter(t => t !== ''),
)


function commit(next: string[]) {
  // De-duplicate case-insensitively, keeping the first spelling entered.
  const seen = new Set<string>()
  const unique = next.filter(t => {
    const key = t.toLowerCase()
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
  emit('update:modelValue', unique.join(props.separator))
}

function addTags(raw: string) {
  const parts = raw.split(',').map(t => t.trim()).filter(t => t !== '')
  if (parts.length > 0) commit([...tags.value, ...parts])
}

function commitDraft() {
  if (props.disabled) return
  addTags(draft.value)
  draft.value = ''
}

function removeAt(index: number) {
  if (props.disabled) return
  commit(tags.value.filter((_, i) => i !== index))
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === ',') {
    event.preventDefault()
    commitDraft()
    return
  }
  // Backspace on an empty input removes the last tag — standard chip behaviour.
  if (event.key === 'Backspace' && draft.value === '' && tags.value.length > 0) {
    event.preventDefault()
    removeAt(tags.value.length - 1)
  }
}

function onPaste(event: ClipboardEvent) {
  const text = event.clipboardData?.getData('text') ?? ''
  if (!text.includes(',')) return
  event.preventDefault()
  addTags(text)
  draft.value = ''
}

function focusInput() {
  if (!props.disabled) inputEl.value?.focus()
}
</script>
