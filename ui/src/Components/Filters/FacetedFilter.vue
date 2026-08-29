<template>
  <div ref="rootRef" class="ui-faceted-filter relative inline-block">
    <!-- Trigger button -->
    <button
      type="button"
      class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border transition-colors focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-0"
      :class="hasValue
        ? 'bg-primary-50 text-primary-700 border-primary-200 hover:bg-primary-100'
        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
      @click="isOpen = !isOpen"
    >
      <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
      </svg>
      <span>{{ label }}</span>

      <!-- Active selection pills (up to 2, then "+N more") -->
      <template v-if="selectedOptions.length > 0">
        <span class="h-px w-px bg-primary-300 mx-0.5" aria-hidden="true" />
        <template v-if="selectedOptions.length <= 2">
          <span
            v-for="opt in selectedOptions"
            :key="String(opt.value)"
            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-primary-100 text-primary-700"
          >
            {{ opt.label }}
          </span>
        </template>
        <span
          v-else
          class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-primary-100 text-primary-700"
        >
          {{ selectedOptions.length }} selected
        </span>
      </template>
    </button>

    <!-- Dropdown -->
    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-show="isOpen"
        class="absolute left-0 z-50 mt-1 w-52 origin-top-left rounded-lg border border-gray-200 bg-white shadow-lg"
      >
        <!-- Search within options (only when many options) -->
        <div v-if="options.length > 6" class="px-2 pt-2">
          <input
            v-model="search"
            type="text"
            placeholder="Search..."
            class="ui-input ui-input-auto w-full px-2.5 py-1.5"
          />
        </div>

        <!-- Options list -->
        <ul class="max-h-64 overflow-y-auto py-1.5 px-1">
          <li v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-gray-400 text-center">
            No results
          </li>
          <li v-for="option in filteredOptions" :key="String(option.value)">
            <button
              type="button"
              class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm text-left transition-colors hover:bg-gray-50"
              @click="toggle(option.value)"
            >
              <!-- Checkbox indicator -->
              <span
                class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors"
                :class="isSelected(option.value)
                  ? 'border-primary-600 bg-primary-600'
                  : 'border-gray-300 bg-white'"
              >
                <svg
                  v-if="isSelected(option.value)"
                  class="h-2.5 w-2.5 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </span>

              <span class="flex-1 truncate font-medium text-gray-700">{{ option.label }}</span>

              <!-- Count badge -->
              <span
                v-if="option.count !== undefined"
                class="ml-auto shrink-0 text-xs tabular-nums text-gray-400"
              >
                {{ option.count }}
              </span>
            </button>
          </li>
        </ul>

        <!-- Footer: clear -->
        <div v-if="hasValue" class="border-t border-gray-100 px-3 py-1.5">
          <button
            type="button"
            class="text-xs text-gray-500 hover:text-gray-900 transition-colors"
            @click="clear"
          >
            Clear filter
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'

export interface FacetOption {
  label: string
  value: string | number
  count?: number
}

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  options: {
    type: Array as () => FacetOption[],
    default: () => [],
  },
  modelValue: {
    type: Array as () => Array<string | number>,
    default: () => [],
  },
})

const emit = defineEmits<{
  'update:modelValue': [value: Array<string | number>]
}>()

const isOpen = ref(false)
const search = ref('')
const rootRef = ref<HTMLElement | null>(null)

const hasValue = computed(() => props.modelValue.length > 0)

const selectedOptions = computed(() =>
  props.options.filter(o => props.modelValue.includes(o.value))
)

const filteredOptions = computed(() => {
  if (!search.value) return props.options
  const q = search.value.toLowerCase()
  return props.options.filter(o => o.label.toLowerCase().includes(q))
})

function isSelected(value: string | number): boolean {
  return props.modelValue.includes(value)
}

function toggle(value: string | number): void {
  const current = [...props.modelValue]
  const idx = current.indexOf(value)
  if (idx === -1) {
    current.push(value)
  } else {
    current.splice(idx, 1)
  }
  emit('update:modelValue', current)
}

function clear(): void {
  emit('update:modelValue', [])
  isOpen.value = false
}

// A press outside closes it, but only while this is the topmost layer — an open
// dialog above the table takes the press instead. Escape closes it too, which
// the bare document listener never handled.
useDismissableLayer(isOpen, {
  elements: () => [rootRef.value],
  onDismiss: () => { isOpen.value = false },
})
</script>
