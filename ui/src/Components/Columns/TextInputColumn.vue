<template>
  <div class="ui-text-input-column inline-flex items-center gap-1.5">
    <input
      ref="input"
      v-model="draft"
      type="text"
      :disabled="disabled || saving"
      :placeholder="placeholder"
      :aria-label="ariaLabel"
      :aria-invalid="failed ? 'true' : undefined"
      class="ui-input ui-text-input-column-input w-full min-w-0 border-transparent bg-transparent px-2 py-1 text-sm hover:border-gray-300 focus:border-primary-600 focus:bg-white disabled:opacity-60"
      :class="{ 'border-danger-600 focus:border-danger-600': failed }"
      @keydown.enter.prevent="commit"
      @keydown.esc.prevent="revert"
      @blur="commit"
      @click.stop
    />

    <svg
      v-if="saving"
      class="h-3.5 w-3.5 shrink-0 animate-spin text-gray-400"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
    </svg>

    <!--
      A failed save is announced rather than only coloured: the row scrolls
      away, and a red border the user has already looked past is not a message.
    -->
    <span v-if="failed" class="sr-only" role="alert">{{ failed }}</span>
    <span
      v-if="failed"
      class="shrink-0 text-xs text-danger-600"
      :title="failed"
      aria-hidden="true"
    >!</span>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = defineProps({
  value: {
    type: [String, Number],
    default: '',
  },
  record: {
    type: Object,
    required: true,
  },
  /** The record key this cell edits. */
  column: {
    type: String,
    required: true,
  },
  placeholder: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  /** Callback run with (record, column, newValue); may reject. */
  onUpdate: {
    type: Function,
    default: null,
  },
})

const emit = defineEmits(['update'])

const input = ref<HTMLInputElement | null>(null)
const draft = ref(String(props.value ?? ''))
const saving = ref(false)
const failed = ref('')

const committed = computed(() => String(props.value ?? ''))

const ariaLabel = computed(() => props.label !== '' ? `${props.label}, editable` : 'Editable cell')

// A value replaced from outside — an optimistic list reconciling, another
// user's change arriving — wins over an untouched draft, but never overwrites
// what someone is in the middle of typing.
watch(committed, (next) => {
  if (document.activeElement !== input.value) draft.value = next
})

function revert(): void {
  draft.value = committed.value
  failed.value = ''
  input.value?.blur()
}

async function commit(): Promise<void> {
  if (props.disabled || saving.value) return

  const next = draft.value.trim()

  // Blur fires on every pass through the cell, so an unchanged value must not
  // cost a request.
  if (next === committed.value) {
    failed.value = ''
    return
  }

  saving.value = true
  failed.value = ''

  try {
    if (props.onUpdate) {
      await props.onUpdate(props.record, props.column, next)
    }

    if (!props.onUpdate) {
    // Emitted only when no `onUpdate` prop was supplied. Vue treats a prop
    // named `onUpdate` as a listener for a declared `update` emit, so doing
    // both called the page's save handler twice — the second time with the
    // event object in place of (record, column, value).
      emit('update', {
        record: props.record,
        column: props.column,
        oldValue: committed.value,
        newValue: next,
      })
    }
  } catch (error) {
    // The draft stays on screen: retyping a rejected edit from memory is worse
    // than seeing it marked as unsaved.
    failed.value = error instanceof Error ? error.message : 'Could not save'
  } finally {
    saving.value = false
  }
}
</script>
