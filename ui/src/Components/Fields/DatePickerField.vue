<template>
  <div ref="root" class="ui-field-date" :class="widthClass" @focusout="onFocusout">
    <label
      v-if="label"
      :for="id"
      class="ui-field-label block text-sm font-medium text-gray-700 mb-1.5"
      :class="{ 'after:content-[\'*\'] after:ml-0.5 after:text-danger-600': required }"
    >
      {{ label }}
    </label>

    <div class="ui-field-wrapper relative">
      <input
        :id="id"
        ref="input"
        v-model="inputText"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        placeholder="dd/mm/yyyy"
        role="combobox"
        aria-haspopup="dialog"
        :aria-expanded="open ? 'true' : 'false'"
        :aria-invalid="invalid ? 'true' : undefined"
        :aria-describedby="describedBy"
        :disabled="disabled"
        :required="required"
        class="ui-input ui-field-input block w-full pr-10"
        :class="{ 'border-danger-600 focus:border-danger-600 focus:ring-danger-600/20': invalid }"
        @input="onInput"
        @keydown="onInputKeydown"
      />

      <button
        type="button"
        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
        :disabled="disabled"
        tabindex="-1"
        aria-label="Toggle calendar"
        @mousedown.prevent
        @click="toggle"
      >
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
      </button>

      <div v-if="open" class="absolute left-0 z-50 mt-1">
        <CalendarPanel
          ref="panel"
          :selected-date="selectedDate"
          :focused-date="focusedDate"
          :min="minDate"
          :max="maxDate"
          @select="onSelect"
          @update:focused-date="focusedDate = $event"
          @close="closeAndRevert"
        />
      </div>
    </div>

    <p v-if="help" :id="`${id}-help`" class="ui-field-help mt-1.5 text-sm text-gray-600">
      {{ help }}
    </p>

    <p v-if="errorText" :id="`${id}-error`" class="ui-field-error mt-1.5 text-sm text-danger-600" role="alert">
      {{ errorText }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useId } from '../../Primitives/useId'
import CalendarPanel from './Calendar/CalendarPanel.vue'
import { useFieldWidth, fieldWidthProp } from './useFieldWidth'
import { formatDisplay, formatISO, parseISO, parseUserInput } from '../../Utils/dates'

const props = defineProps({
  ...fieldWidthProp,
  modelValue: { type: String, default: '' },
  id: { type: String, default: () => useId(undefined, 'field') },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  error: { type: String, default: '' },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const widthClass = useFieldWidth(() => props.width)

const root = ref<HTMLElement | null>(null)
const input = ref<HTMLInputElement | null>(null)
const panel = ref<InstanceType<typeof CalendarPanel> | null>(null)

const open = ref(false)
const selectedDate = ref<Date | null>(parseISO(props.modelValue))
const focusedDate = ref<Date | null>(selectedDate.value)
const inputText = ref(selectedDate.value !== null ? formatDisplay(selectedDate.value) : '')
const parseError = ref(false)

const minDate = computed(() => parseISO(props.min))
const maxDate = computed(() => parseISO(props.max))

const invalid = computed(() => props.error !== '' || parseError.value)
const errorText = computed(() => props.error !== '' ? props.error : (parseError.value ? 'Not a valid date' : ''))
const describedBy = computed(() => {
  const ids = []
  if (props.help) ids.push(`${props.id}-help`)
  if (errorText.value) ids.push(`${props.id}-error`)
  return ids.length > 0 ? ids.join(' ') : undefined
})

// ── Commit points ────────────────────────────────────────────────────────────
// The model only changes here: date tap, Enter, focus leaving the field, Esc.
// Programmatic writes never re-emit, and unparsable text is kept visible in
// an invalid field rather than silently cleared.

const commit = (date: Date | null) => {
  selectedDate.value = date
  focusedDate.value = date
  parseError.value = false
  inputText.value = date !== null ? formatDisplay(date) : ''

  const next = date !== null ? formatISO(date) : ''
  if (next !== props.modelValue) {
    emit('update:modelValue', next)
  }
}

const commitTypedText = () => {
  const text = inputText.value.trim()

  if (text === '') {
    commit(null)
    return
  }

  const parsed = parseUserInput(text)
  if (parsed !== null) {
    commit(parsed)
  } else {
    // Keep the garbage text on screen for correction; the value empties.
    parseError.value = true
    if (props.modelValue !== '') {
      emit('update:modelValue', '')
    }
  }
}

const onSelect = (date: Date) => {
  commit(date)
  open.value = false
  input.value?.focus()
}

// ── Typing ───────────────────────────────────────────────────────────────────

const onInput = () => {
  parseError.value = false
  open.value = true

  // The calendar follows parsable text live, without committing anything.
  const parsed = parseUserInput(inputText.value)
  if (parsed !== null) {
    focusedDate.value = parsed
  }
}

const onInputKeydown = (event: KeyboardEvent) => {
  switch (event.key) {
    case 'ArrowDown':
    case 'ArrowUp':
      event.preventDefault()
      open.value = true
      void panel.value?.focusGrid()
      break
    case 'Enter':
      commitTypedText()
      open.value = false
      break
    case 'Escape':
      if (open.value) {
        closeAndRevert()
      } else if (inputText.value !== '' && selectedDate.value !== null) {
        inputText.value = formatDisplay(selectedDate.value)
        parseError.value = false
      } else if (inputText.value !== '') {
        inputText.value = ''
        parseError.value = false
      }
      break
    case 'Tab':
      open.value = false
      break
  }
}

// ── Open / close ─────────────────────────────────────────────────────────────

const toggle = () => {
  open.value = !open.value
  if (open.value) {
    input.value?.focus()
  }
}

/** Esc / Cancel: throw away navigation, restore the committed state. */
const closeAndRevert = () => {
  open.value = false
  focusedDate.value = selectedDate.value
  inputText.value = selectedDate.value !== null ? formatDisplay(selectedDate.value) : ''
  parseError.value = false
  input.value?.focus()
}

/** Focus left the whole field — commit whatever was typed, close. */
const onFocusout = (event: FocusEvent) => {
  const next = event.relatedTarget
  if (next instanceof Node && root.value?.contains(next)) {
    return
  }

  if (inputText.value.trim() !== (selectedDate.value !== null ? formatDisplay(selectedDate.value) : '')) {
    commitTypedText()
  }
  open.value = false
}

// ── External changes ─────────────────────────────────────────────────────────

watch(() => props.modelValue, (value) => {
  const date = parseISO(value)
  const current = selectedDate.value !== null ? formatISO(selectedDate.value) : ''

  if (value !== current) {
    selectedDate.value = date
    focusedDate.value = date
    inputText.value = date !== null ? formatDisplay(date) : ''
    parseError.value = false
  }
})
</script>
