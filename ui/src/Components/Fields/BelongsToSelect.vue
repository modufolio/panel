<template>
  <FieldPrimitive
    v-bind="{ width, label, required }"
    :id="inputId"
    :help="helperText"
    :error="error || createError"
    wrapper-class="ui-belongs-to-select"
  >
    <!-- The click-outside check needs an element, and a `ref` on the frame
         would hand back the component instance. -->
    <div ref="rootRef" class="relative">
      <!-- Search/Select Input -->
      <div class="relative">
        <input
          :id="inputId"
          v-model="searchQuery"
          type="text"
          role="combobox"
          aria-autocomplete="list"
          :aria-expanded="showDropdown"
          :aria-controls="listboxId"
          :aria-activedescendant="activeDescendant"
          :placeholder="placeholder"
          :disabled="disabled"
          :required="required"
          class="ui-input ui-belongs-to-input block w-full"
          :class="{
            'border-danger-300': error,
            'bg-gray-50 cursor-not-allowed': disabled,
          }"
          @focus="handleFocus"
          @input="handleSearch"
          @keydown="onNavigationKey"
          @keydown.enter.prevent="selectHighlighted"
          @blur="handleBlur"
        />

        <!-- Loading Spinner -->
        <div v-if="loading" class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
          <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <!-- Clear the selection — an optional relation must be able to say "none". -->
        <button
          v-else-if="clearable && selectedValue !== null && selectedValue !== ''"
          type="button"
          class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
          title="Clear selection"
          @click.stop="clearSelection"
        >
          <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
        </button>

        <!-- Dropdown Icon -->
        <div v-else class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
          <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
          </svg>
        </div>
      </div>

      <!-- Dropdown List -->
      <div
        v-if="showDropdown && (filteredOptions.length > 0 || allowCreate || searchUrl)"
        class="ui-belongs-to-dropdown absolute z-50 mt-1 w-full max-h-60 overflow-auto rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5"
      >
        <!-- Options List -->
        <ul :id="listboxId" role="listbox" class="py-1">
          <li
            v-for="(option, index) in filteredOptions"
            :id="optionId(index)"
            :key="valueOf(option) ?? index"
            role="option"
            :aria-selected="option[valueKey] === selectedValue"
            class="ui-belongs-to-option cursor-pointer px-4 py-2 text-sm hover:bg-gray-100 transition-colors"
            :class="{
              'bg-primary-50 text-primary-900': index === highlightedIndex,
              'bg-gray-100': option[valueKey] === selectedValue,
            }"
            @click="selectOption(option)"
            @mouseenter="highlightedIndex = index"
          >
            <div v-if="$slots.option" class="flex items-center">
              <slot name="option" :option="option" />
            </div>
            <div v-else class="flex items-center">
              {{ option[labelKey] }}
            </div>
          </li>

          <!-- No Results -->
          <li v-if="filteredOptions.length === 0 && !allowCreate" role="presentation" class="px-4 py-2 text-sm text-gray-500">
            No results found
          </li>

          <!--
            The cap is stated rather than hidden: a silently truncated list
            reads as "that is everything", which is how someone concludes a
            record does not exist.
          -->
          <li
            v-if="remoteTruncated"
            role="presentation"
            class="border-t border-gray-200 px-4 py-2 text-xs text-gray-500"
          >
            Showing the first {{ filteredOptions.length }} matches — keep typing to narrow it down.
          </li>

          <!-- Create Option — hidden when the exact name already exists,
               because "Create Butter" beside a listed Butter is a trap. -->
          <li
            v-if="showCreateRow"
            :id="optionId(filteredOptions.length)"
            role="option"
            :aria-selected="highlightedIndex === filteredOptions.length"
            class="ui-belongs-to-create cursor-pointer px-4 py-2 text-sm text-primary-600 hover:bg-primary-50 border-t border-gray-200 transition-colors"
            :class="[
              { 'opacity-50 pointer-events-none': creating },
              highlightedIndex === filteredOptions.length ? 'bg-primary-50' : '',
            ]"
            @click="handleCreate"
            @mouseenter="highlightedIndex = filteredOptions.length"
          >
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              {{ creating ? 'Creating…' : `Create "${searchQuery.trim()}"` }}
            </div>
          </li>
        </ul>
      </div>
    </div>

  </FieldPrimitive>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, type PropType } from 'vue'
import { useRelationship } from '../Composables/useRelationship'
import FieldPrimitive from './FieldPrimitive.vue'
import { fieldWidthProp } from './useFieldWidth'
import { apiFetch, ApiError } from '../../Utils/apiFetch'
import { useId } from '../../Primitives/useId'
import { resolveNavigationIndex } from '../../Primitives/useArrowNavigation'
import { isEscapeKey, useDismissableLayer } from '../../Primitives/useDismissableLayer'

/**
 * An option row, keyed by whatever `valueKey` / `labelKey` name — the shape
 * is the consumer's, so only the two keys the field reads are interpreted.
 */
type BelongsToOption = Record<string, unknown>

const props = defineProps({
  ...fieldWidthProp,
  modelValue: {
    type: [String, Number, null],
    default: null,
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Search or select...',
  },
  helperText: {
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
  /**
   * Options array or endpoint for fetching options
   */
  options: {
    type: Array as PropType<BelongsToOption[]>,
    default: () => [],
  },
  endpoint: {
    type: String,
    default: null,
  },
  /**
   * Server-side search endpoint for a relation too large to preload.
   *
   * Answers `?q=` with matches and `?values=` with the labels for identifiers
   * the field already holds — the second is what lets an edit form show its
   * current selection when the list it came from was never sent.
   */
  searchUrl: {
    type: String,
    default: null,
  },
  /**
   * Offer an explicit way back to "no selection".
   *
   * A lookup can otherwise only ever tighten: once a value is chosen, the
   * input shows its label and there is nothing to press to mean "none". Set
   * for optional relations.
   */
  clearable: {
    type: Boolean,
    default: false,
  },
  relationship: {
    type: String,
    default: null,
  },
  /**
   * Keys for option object
   */
  valueKey: {
    type: String,
    default: 'id',
  },
  labelKey: {
    type: String,
    default: 'name',
  },
  /**
   * Search configuration
   */
  searchable: {
    type: Boolean,
    default: true,
  },
  /**
   * Allow creating new records
   */
  allowCreate: {
    type: Boolean,
    default: false,
  },
  /**
   * Minimum characters before searching
   */
  minSearchLength: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['update:modelValue', 'create'])


const rootRef = ref<HTMLElement | null>(null)
const inputId = useId(undefined, 'belongs-to')
const listboxId = `${inputId}-listbox`
const optionId = (index: number): string => `${inputId}-option-${index}`
const searchQuery = ref('')
const showDropdown = ref(false)
const highlightedIndex = ref(0)
const selectedValue = ref(props.modelValue)

/** The option's display text; a label is a string on the wire, anything else is shown as text. */
const labelOf = (option: BelongsToOption): string => String(option[props.labelKey] ?? '')

/** The option's identifier — a string or number, as a foreign key is. */
const valueOf = (option: BelongsToOption): string | number | null => {
  const value = option[props.valueKey]
  return typeof value === 'string' || typeof value === 'number' ? value : null
}

/**
 * The held value's label, kept separately from the option lists: a remote
 * search replaces `remoteOptions` wholesale, so the selected option can
 * vanish from the list while the selection itself stands — and the input
 * must still know what to call it.
 */
const selectedLabel = ref('')

/**
 * Close without a selection: the input reverts to naming what is actually
 * held. Without this, typed-but-never-chosen text survives as the display
 * while the value underneath stays what it was — the input lying about the
 * selection, which a submit would then quietly prove.
 */
const closeAndRevert = () => {
  showDropdown.value = false

  searchQuery.value = selectedValue.value !== null && selectedValue.value !== ''
    ? selectedLabel.value
    : ''
}

// Initialize relationship composable if endpoint is provided
const relationshipComposable = props.endpoint
  ? useRelationship({
      endpoint: props.endpoint,
      relationship: props.relationship,
      searchable: props.searchable,
    })
  : null

/** Results of the current server search, plus labels for the held value. */
const remoteOptions = ref<BelongsToOption[]>([])
const remoteLoading = ref(false)
const remoteTruncated = ref(false)

const loading = computed(() => relationshipComposable?.loading.value || remoteLoading.value || false)

// Use either provided options or fetched options
const availableOptions = computed(() => {
  if (props.searchUrl) {
    return remoteOptions.value
  }
  if (props.options.length > 0) {
    return props.options
  }
  return relationshipComposable?.records.value || []
})

// Filter options based on search query
const filteredOptions = computed(() => {
  // The server already applied the term; filtering again here would hide
  // matches it made on grounds the client cannot see.
  if (props.searchUrl) {
    return availableOptions.value
  }

  if (!props.searchable || !searchQuery.value) {
    return availableOptions.value
  }

  const query = searchQuery.value.toLowerCase()
  return availableOptions.value.filter((option) =>
    labelOf(option).toLowerCase().includes(query)
  )
})

/** Fetch from the relation endpoint, keyed by query string. */
async function fetchRemote(params: string): Promise<BelongsToOption[]> {
  const url = `${props.searchUrl}${props.searchUrl!.includes('?') ? '&' : '?'}${params}`

  const response = await fetch(url, {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })

  if (!response.ok) {
    throw new Error(`Relation search failed with status ${response.status}`)
  }

  const body: { data?: unknown; meta?: { truncated?: unknown } } | null = await response.json()
  remoteTruncated.value = body?.meta?.truncated === true

  const data = body?.data
  return Array.isArray(data) ? data : []
}

/** Debounced so typing does not issue a request per keystroke. */
let searchTimer: ReturnType<typeof setTimeout> | undefined

function scheduleRemoteSearch(term: string): void {
  if (searchTimer !== undefined) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(async () => {
    remoteLoading.value = true
    try {
      remoteOptions.value = await fetchRemote(`q=${encodeURIComponent(term)}`)
    } catch (error) {
      console.error(error)
      remoteOptions.value = []
    } finally {
      remoteLoading.value = false
    }
  }, 200)
}

// Get selected option object
const selectedOption = computed(() => {
  return availableOptions.value.find(
    (option) => option[props.valueKey] === selectedValue.value
  )
})

/**
 * Opening a searchable relation shows a first page rather than an empty box —
 * the control must be usable by someone who does not yet know what to type.
 */
const handleFocus = () => {
  showDropdown.value = true

  if (props.searchUrl && remoteOptions.value.length <= 1 && !remoteLoading.value) {
    const selected = selectedOption.value
    scheduleRemoteSearch(selected !== undefined && searchQuery.value === labelOf(selected) ? '' : searchQuery.value)
  }
}

// Handle search input
const handleSearch = () => {
  highlightedIndex.value = 0
  // A failed create's message describes the previous attempt; typing starts
  // a new one.
  createError.value = ''

  // Emptying the input deselects — the only way to unset an optional relation.
  if (searchQuery.value === '' && selectedValue.value !== null && selectedValue.value !== '') {
    selectedValue.value = null
    emit('update:modelValue', null)
  }

  if (props.searchUrl && searchQuery.value.length >= props.minSearchLength) {
    scheduleRemoteSearch(searchQuery.value)
    return
  }

  if (props.endpoint && searchQuery.value.length >= props.minSearchLength) {
    relationshipComposable?.search(searchQuery.value)
  }
}

/**
 * Everything the arrow keys can land on: the matches, plus the "Create …" row
 * when it is offered — it is an option like any other, and a keyboard user
 * could not reach it while it was only clickable.
 */
const navigableCount = computed(() => filteredOptions.value.length + (showCreateRow.value ? 1 : 0))

/**
 * The option the input is pointing at. Screen readers announce the highlighted
 * row from this — without it the list moves silently, because focus never
 * leaves the text input.
 */
const activeDescendant = computed(() =>
  showDropdown.value && navigableCount.value > 0 ? optionId(highlightedIndex.value) : undefined,
)

const onNavigationKey = (event: KeyboardEvent) => {
  if (isEscapeKey(event)) {
    closeAndRevert()
    return
  }

  const next = resolveNavigationIndex(event.key, highlightedIndex.value, navigableCount.value, { loop: false })
  if (next === -1) return

  event.preventDefault()

  if (!showDropdown.value) showDropdown.value = true
  highlightedIndex.value = next
}

const selectHighlighted = () => {
  if (showCreateRow.value && highlightedIndex.value === filteredOptions.value.length) {
    handleCreate()
    return
  }

  if (filteredOptions.value[highlightedIndex.value]) {
    selectOption(filteredOptions.value[highlightedIndex.value])
  }
}

// Select an option
const selectOption = (option: BelongsToOption) => {
  selectedValue.value = valueOf(option)
  selectedLabel.value = labelOf(option)
  searchQuery.value = labelOf(option)
  showDropdown.value = false
  emit('update:modelValue', selectedValue.value)
}

const clearSelection = () => {
  selectedValue.value = null
  selectedLabel.value = ''
  searchQuery.value = ''
  showDropdown.value = false
  emit('update:modelValue', null)
}

/**
 * Blur reverts like Esc does — but only when focus left the control
 * entirely. A click on a dropdown option blurs the input first, and
 * reverting then would repaint the list from under the click.
 */
const handleBlur = (event: FocusEvent) => {
  const next = event.relatedTarget as HTMLElement | null

  if (next?.closest('.ui-belongs-to-select') === null || next === null) {
    // Let an option click land before deciding nothing was chosen.
    setTimeout(() => {
      if (!showDropdown.value) return
      closeAndRevert()
    }, 150)
  }
}

/**
 * "Create …" is offered only for a name the list does not already carry —
 * offering to create what exists invites duplicates the server would then
 * dedupe by returning the existing row, which is correct and confusing.
 */
const showCreateRow = computed(() => {
  if (!props.allowCreate || searchQuery.value.trim() === '') return false

  const query = searchQuery.value.trim().toLowerCase()

  return !filteredOptions.value.some(
    (option) => labelOf(option).toLowerCase() === query,
  )
})

const creating = ref(false)
const createError = ref('')

/**
 * Make the record the user just named.
 *
 * With a `searchUrl`, the same endpoint accepts a POST — the server
 * re-checks that this target really is creatable from a label alone, so a
 * forged offer buys nothing. Without one (legacy consumers), the event is
 * emitted for the parent to handle as before.
 */
const handleCreate = async () => {
  const label = searchQuery.value.trim()

  if (!props.searchUrl) {
    emit('create', label)
    showDropdown.value = false
    return
  }

  if (label === '' || creating.value) return

  creating.value = true
  createError.value = ''

  try {
    const body = await apiFetch(props.searchUrl, { method: 'POST', body: { label } }) as {
      data: Record<string, string>
    }

    const option = body.data

    // Into the list before selecting, so the selection can resolve its label.
    if (!remoteOptions.value.some((o) => o[props.valueKey] === option[props.valueKey])) {
      remoteOptions.value = [...remoteOptions.value, option]
    }

    selectOption(option)
  } catch (error) {
    const body = error instanceof ApiError ? error.body : null
    const message = body !== null && typeof body === 'object' && 'error' in body ? body.error : null
    createError.value = typeof message === 'string' ? message : 'Could not create it.'
  } finally {
    creating.value = false
  }
}

// Closing by pressing outside reverts like Esc, since walking away from an open
// dropdown is the same "never mind" as dismissing it. Registering as a layer
// means an open dialog above this field takes the press instead.
useDismissableLayer(showDropdown, {
  elements: () => [rootRef.value],
  onDismiss: closeAndRevert,
})

// Watch for modelValue changes
watch(() => props.modelValue, (newValue) => {
  selectedValue.value = newValue
  if (newValue && selectedOption.value) {
    selectedLabel.value = labelOf(selectedOption.value)
    searchQuery.value = labelOf(selectedOption.value)
  } else if (!newValue) {
    selectedLabel.value = ''
    searchQuery.value = ''
  }
})

// Initialize
onMounted(async () => {
  if (props.searchUrl) {
    // A held value arrives as a bare identifier — the list it came from was
    // never sent — so ask the server what to call it before anything renders.
    if (props.modelValue !== null && props.modelValue !== '') {
      try {
        remoteOptions.value = await fetchRemote(
          `values=${encodeURIComponent(String(props.modelValue))}`,
        )
        if (selectedOption.value) {
          selectedLabel.value = labelOf(selectedOption.value)
          searchQuery.value = labelOf(selectedOption.value)
        }
      } catch (error) {
        console.error(error)
      }
    }

    return
  }

  // Fetch options if endpoint is provided
  if (props.endpoint && !props.options.length) {
    await relationshipComposable?.fetchRecords()
  }

  // Set initial search query from selected value
  if (props.modelValue && selectedOption.value) {
    selectedLabel.value = labelOf(selectedOption.value)
    searchQuery.value = labelOf(selectedOption.value)
  }
})

onUnmounted(() => {
  if (searchTimer !== undefined) {
    clearTimeout(searchTimer)
  }
})
</script>