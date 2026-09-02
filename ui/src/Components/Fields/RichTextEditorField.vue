<template>
  <FieldPrimitive
    v-bind="{ width, label, help, error, required }"
    wrapper-class="ui-field-rich-text space-y-1.5 border-0 p-0 m-0"
    as="fieldset"
    label-spacing="none"
  >
    <div
      class="ui-input overflow-hidden p-0"
      :class="{ 'border-danger-600': error, 'bg-gray-50': disabled && !error }"
    >
      <!-- Toolbar -->
      <div class="border-b border-gray-200 bg-gray-50 px-3 py-2">
        <div class="flex flex-wrap items-center gap-1">
          <!-- Text Formatting -->
          <div class="flex items-center gap-0.5 border-r border-gray-300 pr-2">
            <ToolbarButton
              title="Bold"
              :disabled="disabled"
              @click="insertMarkdown('**', '**', 'Bold text')"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path
                  d="M5 3a1 1 0 000 2h1.586l-1.293 1.293a1 1 0 101.414 1.414L8 6.414V8a1 1 0 102 0V5a1 1 0 00-1-1H5zM3 12a1 1 0 011-1h1a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm8-1a1 1 0 00-1 1v3a1 1 0 001 1h5a1 1 0 001-1v-3a1 1 0 00-1-1h-5z"
                />
              </svg>
            </ToolbarButton>
            <ToolbarButton
              title="Italic"
              :disabled="disabled"
              @click="insertMarkdown('*', '*', 'Italic text')"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path
                  d="M11.5 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zM7.5 5a1 1 0 000 2h1.586l-2.793 2.793a1 1 0 101.414 1.414L10.5 8.414V10a1 1 0 102 0V5a1 1 0 00-1-1h-4z"
                />
              </svg>
            </ToolbarButton>
            <ToolbarButton
              title="Strikethrough"
              :disabled="disabled"
              @click="insertMarkdown('~~', '~~', 'Strikethrough text')"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
            </ToolbarButton>
          </div>

          <!-- Headings -->
          <div class="flex items-center gap-0.5 border-r border-gray-300 pr-2">
            <ToolbarButton
              title="Heading 1"
              :disabled="disabled"
              @click="insertHeading(1)"
            >
              H1
            </ToolbarButton>
            <ToolbarButton
              title="Heading 2"
              :disabled="disabled"
              @click="insertHeading(2)"
            >
              H2
            </ToolbarButton>
            <ToolbarButton
              title="Heading 3"
              :disabled="disabled"
              @click="insertHeading(3)"
            >
              H3
            </ToolbarButton>
          </div>

          <!-- Lists -->
          <div class="flex items-center gap-0.5 border-r border-gray-300 pr-2">
            <ToolbarButton
              title="Bullet List"
              :disabled="disabled"
              @click="insertList('unordered')"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fill-rule="evenodd"
                  d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                  clip-rule="evenodd"
                />
              </svg>
            </ToolbarButton>
            <ToolbarButton
              title="Numbered List"
              :disabled="disabled"
              @click="insertList('ordered')"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fill-rule="evenodd"
                  d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                  clip-rule="evenodd"
                />
              </svg>
            </ToolbarButton>
          </div>

          <!-- Links & Code -->
          <div class="flex items-center gap-0.5 border-r border-gray-300 pr-2">
            <ToolbarButton
              title="Link"
              :disabled="disabled"
              @click="insertLink"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                />
              </svg>
            </ToolbarButton>
            <ToolbarButton
              title="Code"
              :disabled="disabled"
              @click="insertMarkdown('`', '`', 'code')"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                />
              </svg>
            </ToolbarButton>
            <ToolbarButton
              title="Code Block"
              :disabled="disabled"
              @click="insertCodeBlock"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fill-rule="evenodd"
                  d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z"
                  clip-rule="evenodd"
                />
              </svg>
            </ToolbarButton>
          </div>

          <!-- Quote & HR -->
          <div class="flex items-center gap-0.5">
            <ToolbarButton
              title="Quote"
              :disabled="disabled"
              @click="insertQuote"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fill-rule="evenodd"
                  d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2zM5 7a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H6z"
                  clip-rule="evenodd"
                />
              </svg>
            </ToolbarButton>
            <ToolbarButton
              title="Horizontal Rule"
              :disabled="disabled"
              @click="insertHorizontalRule"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 12h14"
                />
              </svg>
            </ToolbarButton>
          </div>
        </div>
      </div>

      <!-- Editor -->
      <textarea
        ref="textarea"
        v-model="localValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :rows="rows"
        class="block w-full resize-y border-0 p-4 text-sm focus:outline-none focus:ring-0"
        :class="[
          disabled ? 'bg-gray-50 text-gray-500' : 'bg-white text-gray-900',
        ]"
        @input="handleInput"
      />

      <!-- Footer with character count -->
      <div
        v-if="showCharCount"
        class="border-t border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500"
      >
        {{ characterCount }} character{{ characterCount !== 1 ? 's' : '' }}
        <span v-if="maxLength">/ {{ maxLength }}</span>
      </div>
    </div>

  </FieldPrimitive>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import FieldPrimitive from '../../Components/Fields/FieldPrimitive.vue'
import { fieldWidthProp } from '../../Components/Fields/useFieldWidth'

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Enter text...',
  },
  help: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  rows: {
    type: Number,
    default: 10,
  },
  maxLength: {
    type: Number,
    default: null,
  },
  showCharCount: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue'])


// State
const textarea = ref<HTMLTextAreaElement | null>(null)
const localValue = ref(props.modelValue || '')

// Computed
const characterCount = computed(() => localValue.value.length)

// Functions
function handleInput() {
  if (props.maxLength && localValue.value.length > props.maxLength) {
    localValue.value = localValue.value.substring(0, props.maxLength)
  }
  emit('update:modelValue', localValue.value)
}

function insertMarkdown(before: string, after: string, placeholder: string) {
  const element = textarea.value
  if (!element) return
  const start = element.selectionStart
  const end = element.selectionEnd
  const selectedText = localValue.value.substring(start, end) || placeholder

  const newText =
    localValue.value.substring(0, start) +
    before +
    selectedText +
    after +
    localValue.value.substring(end)

  localValue.value = newText
  emit('update:modelValue', newText)

  // Restore cursor position
  setTimeout(() => {
    element.focus()
    element.setSelectionRange(
      start + before.length,
      start + before.length + selectedText.length
    )
  }, 0)
}

function insertHeading(level: number) {
  const element = textarea.value
  if (!element) return
  const start = element.selectionStart
  const lineStart = localValue.value.lastIndexOf('\n', start - 1) + 1
  const prefix = '#'.repeat(level) + ' '

  const newText =
    localValue.value.substring(0, lineStart) +
    prefix +
    localValue.value.substring(lineStart)

  localValue.value = newText
  emit('update:modelValue', newText)

  setTimeout(() => {
    element.focus()
    element.setSelectionRange(start + prefix.length, start + prefix.length)
  }, 0)
}

function insertList(type: string) {
  const element = textarea.value
  if (!element) return
  const start = element.selectionStart
  const lineStart = localValue.value.lastIndexOf('\n', start - 1) + 1
  const prefix = type === 'ordered' ? '1. ' : '- '

  const newText =
    localValue.value.substring(0, lineStart) +
    prefix +
    localValue.value.substring(lineStart)

  localValue.value = newText
  emit('update:modelValue', newText)

  setTimeout(() => {
    element.focus()
    element.setSelectionRange(start + prefix.length, start + prefix.length)
  }, 0)
}

function insertLink() {
  const element = textarea.value
  if (!element) return
  const start = element.selectionStart
  const end = element.selectionEnd
  const selectedText = localValue.value.substring(start, end) || 'link text'

  const linkMarkdown = `[${selectedText}](url)`

  const newText =
    localValue.value.substring(0, start) +
    linkMarkdown +
    localValue.value.substring(end)

  localValue.value = newText
  emit('update:modelValue', newText)

  setTimeout(() => {
    element.focus()
    // Select "url" part
    element.setSelectionRange(
      start + selectedText.length + 3,
      start + selectedText.length + 6
    )
  }, 0)
}

function insertCodeBlock() {
  const element = textarea.value
  if (!element) return
  const start = element.selectionStart
  const end = element.selectionEnd
  const selectedText = localValue.value.substring(start, end) || 'code'

  const codeBlock = `\`\`\`\n${selectedText}\n\`\`\``

  const newText =
    localValue.value.substring(0, start) +
    codeBlock +
    localValue.value.substring(end)

  localValue.value = newText
  emit('update:modelValue', newText)

  setTimeout(() => {
    element.focus()
    element.setSelectionRange(
      start + 4,
      start + 4 + selectedText.length
    )
  }, 0)
}

function insertQuote() {
  const element = textarea.value
  if (!element) return
  const start = element.selectionStart
  const lineStart = localValue.value.lastIndexOf('\n', start - 1) + 1

  const newText =
    localValue.value.substring(0, lineStart) +
    '> ' +
    localValue.value.substring(lineStart)

  localValue.value = newText
  emit('update:modelValue', newText)

  setTimeout(() => {
    element.focus()
    element.setSelectionRange(start + 2, start + 2)
  }, 0)
}

function insertHorizontalRule() {
  const element = textarea.value
  if (!element) return
  const start = element.selectionStart

  const newText =
    localValue.value.substring(0, start) +
    '\n---\n' +
    localValue.value.substring(start)

  localValue.value = newText
  emit('update:modelValue', newText)

  setTimeout(() => {
    element.focus()
    element.setSelectionRange(start + 5, start + 5)
  }, 0)
}

// Watch for external changes
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue !== localValue.value) {
      localValue.value = newValue || ''
    }
  }
)
</script>

<script lang="ts">
import { defineComponent, h } from 'vue'

// Toolbar button. A render function rather than a template string: the
// latter needs Vue's runtime compiler, which a runtime-only host build lacks.
export const ToolbarButton = defineComponent({
  props: {
    title: { type: String, default: undefined },
    disabled: { type: Boolean, default: false },
  },
  setup(props, { slots }) {
    return () =>
      h(
        'button',
        {
          type: 'button',
          title: props.title,
          disabled: props.disabled,
          class:
            'rounded p-1.5 text-gray-600 transition-colors duration-200 hover:bg-gray-200 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent',
        },
        slots.default?.(),
      )
  },
})
</script>
