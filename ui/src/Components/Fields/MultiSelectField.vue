<template>
  <FieldPrimitive
    v-bind="{ width, label, help, error, required }"
    wrapper-class="ui-field-multiselect space-y-1.5 border-0 p-0 m-0"
    as="fieldset"
    label-spacing="none"
  >
    <div ref="rootRef" class="relative">
      <!-- Selected Items Display -->
      <div
        class="min-h-10.5 w-full rounded-xs border shadow-sm transition-colors duration-200 focus-within:ring-2 focus-within:ring-offset-0"
        :class="[
          error
            ? 'border-danger-600 focus-within:border-danger-600 focus-within:ring-danger-600/20'
            : disabled
            ? 'border-gray-300 bg-gray-50'
            : 'border-gray-300 focus-within:border-primary-600 focus-within:ring-primary-600/20',
          isOpen ? 'rounded-b-none' : '',
        ]"
      >
        <div class="flex flex-wrap gap-1.5 p-2">
          <!-- Selected Tags -->
          <template v-if="selectedValues.length > 0">
            <div
              v-for="value in selectedValues"
              :key="value"
              class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-1 text-sm font-medium text-primary-700"
            >
              <span>{{ getOptionLabel(value) }}</span>
              <button
                v-if="!disabled"
                type="button"
                @click.stop="removeValue(value)"
                class="rounded hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                <svg
                  class="h-3.5 w-3.5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </template>

          <!-- Search Input -->
          <input
            :id="inputId"
            v-model="searchQuery"
            type="text"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="isOpen"
            :aria-controls="listboxId"
            :aria-activedescendant="activeDescendant"
            :placeholder="selectedValues.length === 0 ? placeholder : ''"
            :disabled="disabled"
            class="min-w-30 flex-1 border-0 bg-transparent p-0 text-sm placeholder-gray-400 focus:outline-none focus:ring-0"
            @focus="openDropdown"
            @keydown="onNavigationKey"
            @keydown.enter.prevent="selectHighlighted"
            @keydown.escape="closeDropdown"
            @keydown.backspace="handleBackspace"
          />
        </div>
      </div>

      <!-- Dropdown -->
      <Transition
        enter-active-class="transition duration-100 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-75 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-show="isOpen && !disabled"
          class="absolute z-10 mt-0 w-full rounded-b-lg border border-t-0 border-gray-300 bg-white shadow-lg"
        >
          <div :id="listboxId" role="listbox" aria-multiselectable="true" class="max-h-60 overflow-auto py-1">
            <!-- Options -->
            <template v-if="filteredOptions.length > 0">
              <button
                v-for="(option, index) in filteredOptions"
                :id="optionId(index)"
                :key="option.value"
                type="button"
                role="option"
                :aria-selected="isSelected(option.value)"
                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition-colors"
                :class="[
                  highlightedIndex === index
                    ? 'bg-primary-50 text-primary-900'
                    : 'text-gray-900 hover:bg-gray-50',
                  isSelected(option.value) ? 'font-medium' : 'font-normal',
                ]"
                @click="toggleValue(option.value)"
                @mouseenter="highlightedIndex = index"
              >
                <span>{{ option.label }}</span>
                <svg
                  v-if="isSelected(option.value)"
                  class="h-5 w-5 text-primary-600"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path
                    fill-rule="evenodd"
                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                    clip-rule="evenodd"
                  />
                </svg>
              </button>
            </template>

            <!-- No Results -->
            <div
              v-else
              class="px-3 py-8 text-center text-sm text-gray-500"
            >
              {{ searchQuery ? 'No results found' : 'No options available' }}
            </div>
          </div>

          <!--
            The cap is stated rather than hidden: a silently truncated list
            reads as "that is everything", which is how someone concludes a
            record does not exist.
          -->
          <div
            v-if="remoteTruncated"
            class="border-t border-gray-200 px-3 py-2 text-xs text-gray-500"
          >
            Showing the first {{ filteredOptions.length }} matches — keep typing to narrow it down.
          </div>

          <!-- Select All / Clear All -->
          <div
            v-if="filteredOptions.length > 0"
            class="border-t border-gray-200 bg-gray-50 px-3 py-2"
          >
            <div class="flex items-center justify-between text-xs">
              <!--
                Hidden for a searchable relation: "all" could only ever mean
                "the page currently loaded", which is not what it says.
              -->
              <button
                v-if="!searchUrl && selectedValues.length < normalizedOptions.length"
                type="button"
                class="font-medium text-primary-600 hover:text-primary-700"
                @click="selectAll"
              >
                Select All
              </button>
              <button
                v-if="selectedValues.length > 0"
                type="button"
                class="font-medium text-gray-600 hover:text-gray-700"
                @click="clearAll"
              >
                Clear All
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Selected Count -->
    <p v-if="selectedValues.length > 0 && !error" class="text-xs text-gray-500">
      {{ selectedValues.length }} item{{ selectedValues.length !== 1 ? 's' : '' }} selected
    </p>
  </FieldPrimitive>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, type PropType } from 'vue'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'
import { resolveNavigationIndex } from '../../Primitives/useArrowNavigation'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { useId } from '../../Primitives/useId'

interface SelectOption {
  label: string
  value: string | number
}

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: Array as PropType<(string | number)[]>,
    default: () => [],
  },
  label: {
    type: String,
    default: '',
  },
  options: {
    type: Array as PropType<Array<SelectOption | string>>,
    required: true as const,
  },
  placeholder: {
    type: String,
    default: 'Select options...',
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
  searchable: {
    type: Boolean,
    default: true,
  },
  /**
   * Server-side search endpoint for a relation too large to preload.
   *
   * Answers `?q=` with matches and `?values=` with the labels for identifiers
   * already selected — without the second, chips for a held selection could
   * only show raw identifiers.
   */
  searchUrl: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])


// State
const rootRef = ref<HTMLElement | null>(null)
const inputId = useId(undefined, 'multi-select')
const listboxId = `${inputId}-listbox`
const optionId = (index: number): string => `${inputId}-option-${index}`
const isOpen = ref(false)
const searchQuery = ref('')
const highlightedIndex = ref(0)
const selectedValues = ref<(string | number)[]>([...props.modelValue as (string | number)[]])

/**
 * Server-search state. `knownOptions` accumulates every option this field has
 * ever seen — search results plus the labels resolved for the initial
 * selection — because a chip must keep its label after the search that
 * produced it has been replaced by another.
 */
const remoteResults = ref<SelectOption[]>([])
const knownOptions = ref<SelectOption[]>([])
const remoteTruncated = ref(false)

// Normalize options to { label, value } format
const normalizedOptions = computed<SelectOption[]>(() => {
  const declared = props.options.map((option) => {
    if (typeof option === 'string') {
      return { label: option, value: option }
    }
    return option
  })

  return props.searchUrl ? knownOptions.value : declared
})

// Filter options based on search query
const filteredOptions = computed<SelectOption[]>(() => {
  // The server already applied the term; the visible list is exactly what it
  // returned, plus nothing.
  if (props.searchUrl) {
    return remoteResults.value
  }

  if (!props.searchable || !searchQuery.value) {
    return normalizedOptions.value
  }

  const query = searchQuery.value.toLowerCase()
  return normalizedOptions.value.filter((option) =>
    option.label.toLowerCase().includes(query)
  )
})

/** Merge newly seen options into the label cache, keeping first labels. */
function rememberOptions(options: SelectOption[]): void {
  const seen = new Set(knownOptions.value.map((option) => String(option.value)))

  knownOptions.value = [
    ...knownOptions.value,
    ...options.filter((option) => !seen.has(String(option.value))),
  ]
}

async function fetchRemote(params: string): Promise<SelectOption[]> {
  const url = `${props.searchUrl}${props.searchUrl!.includes('?') ? '&' : '?'}${params}`

  const response = await fetch(url, {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })

  if (!response.ok) {
    throw new Error(`Relation search failed with status ${response.status}`)
  }

  const body = await response.json()
  remoteTruncated.value = body?.meta?.truncated === true

  const options: SelectOption[] = Array.isArray(body?.data) ? body.data : []
  rememberOptions(options)

  return options
}

/** Debounced so typing does not issue a request per keystroke. */
let searchTimer: ReturnType<typeof setTimeout> | undefined

function scheduleRemoteSearch(term: string): void {
  if (searchTimer !== undefined) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(async () => {
    try {
      remoteResults.value = await fetchRemote(`q=${encodeURIComponent(term)}`)
    } catch (error) {
      console.error(error)
      remoteResults.value = []
    }
  }, 200)
}

// Helper functions
function getOptionLabel(value: string | number) {
  const option = normalizedOptions.value.find((opt) => opt.value === value)
  return option ? option.label : String(value)
}

function isSelected(value: string | number) {
  return selectedValues.value.includes(value)
}

function toggleValue(value: string | number) {
  if (isSelected(value)) {
    removeValue(value)
  } else {
    addValue(value)
  }
}

function addValue(value: string | number) {
  const newValues = [...selectedValues.value, value]
  selectedValues.value = newValues
  emit('update:modelValue', newValues)
  searchQuery.value = ''
  highlightedIndex.value = 0
}

function removeValue(value: string | number) {
  const newValues = selectedValues.value.filter((v) => v !== value)
  selectedValues.value = newValues
  emit('update:modelValue', newValues)
}

function selectAll() {
  const allValues = normalizedOptions.value.map((opt) => opt.value)
  selectedValues.value = allValues
  emit('update:modelValue', allValues)
  searchQuery.value = ''
}

function clearAll() {
  selectedValues.value = []
  emit('update:modelValue', [])
  searchQuery.value = ''
}

function openDropdown() {
  if (!props.disabled) {
    isOpen.value = true
    highlightedIndex.value = 0

    // Show a first page rather than an empty box: the control has to be
    // usable by someone who does not yet know what to type.
    if (props.searchUrl && remoteResults.value.length === 0) {
      scheduleRemoteSearch(searchQuery.value)
    }
  }
}

function closeDropdown() {
  isOpen.value = false
  searchQuery.value = ''
  highlightedIndex.value = 0
}

/**
 * The option the input points at. Focus never leaves the text field, so this is
 * the only thing telling a screen reader which row is highlighted.
 */
const activeDescendant = computed(() =>
  isOpen.value && filteredOptions.value.length > 0 ? optionId(highlightedIndex.value) : undefined,
)

function onNavigationKey(event: KeyboardEvent) {
  const next = resolveNavigationIndex(
    event.key,
    highlightedIndex.value,
    filteredOptions.value.length,
    { loop: false },
  )
  if (next === -1) return

  event.preventDefault()

  if (!isOpen.value) {
    openDropdown()
    return
  }

  highlightedIndex.value = next
}

function selectHighlighted() {
  if (isOpen.value && filteredOptions.value[highlightedIndex.value]) {
    toggleValue(filteredOptions.value[highlightedIndex.value].value)
  }
}

function handleBackspace() {
  if (searchQuery.value === '' && selectedValues.value.length > 0) {
    removeValue(selectedValues.value[selectedValues.value.length - 1])
  }
}

// Registering as a layer, rather than listening on `document`, means an open
// dialog above this field takes an outside press instead of both reacting.
useDismissableLayer(isOpen, {
  elements: () => [rootRef.value],
  onDismiss: closeDropdown,
})

// Watch for external changes
watch(
  () => props.modelValue,
  (newValue) => {
    selectedValues.value = [...newValue]
  }
)

/** Typing in a searchable relation asks the server, not the local list. */
watch(searchQuery, (term) => {
  if (props.searchUrl) {
    scheduleRemoteSearch(term)
  }
})

/**
 * A searchable field's chips arrive as bare identifiers — the list they came
 * from was never sent — so ask the server what to call them.
 */
onMounted(async () => {
  if (props.searchUrl && selectedValues.value.length > 0) {
    try {
      await fetchRemote(`values=${encodeURIComponent(selectedValues.value.join(','))}`)
    } catch (error) {
      console.error(error)
    }
  }
})

onBeforeUnmount(() => {
  if (searchTimer !== undefined) {
    clearTimeout(searchTimer)
  }
})
</script>
