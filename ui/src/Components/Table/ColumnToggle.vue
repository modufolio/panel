<template>
  <div class="relative">
    <button
      ref="triggerRef"
      type="button"
      class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-0"
      @click="toggleDropdown"
    >
      <svg
        class="h-4 w-4"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"
        />
      </svg>
      <span>Columns</span>
      <span
        v-if="hiddenCount > 0"
        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
      >
        {{ hiddenCount }} hidden
      </span>
    </button>

    <!-- Dropdown -->
    <Teleport :to="teleportTarget">
      <Transition
        enter-active-class="transition duration-100 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-75 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
      <div
        v-show="isOpen"
        ref="dropdownRef"
        class="z-50 flex w-64 flex-col origin-top-right rounded-lg border border-gray-200 bg-white shadow-lg"
        :style="floatingStyles"
      >
        <div class="flex min-h-0 flex-1 flex-col p-3">
          <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-900">Toggle Columns</h3>
            <button
              v-if="hiddenCount > 0"
              type="button"
              class="text-xs font-medium text-primary-600 hover:text-primary-700"
              @click="showAll"
            >
              Show All
            </button>
          </div>

          <div class="min-h-0 flex-1 space-y-1 overflow-y-auto">
            <label
              v-for="column in columns"
              :key="column.key"
              class="flex cursor-pointer items-center gap-3 rounded-md px-3 py-2 transition-colors hover:bg-gray-50"
              :class="{ 'opacity-50': !canToggle(column) }"
            >
              <input
                type="checkbox"
                :checked="isVisible(column)"
                :disabled="!canToggle(column)"
                @change="toggleColumn(column)"
                class="rounded border-gray-300 text-primary-600 focus:ring-primary-600 disabled:cursor-not-allowed disabled:opacity-50"
              />
              <span class="flex-1 text-sm text-gray-700">
                {{ column.label }}
              </span>
              <span
                v-if="!canToggle(column)"
                class="text-xs text-gray-400"
              >
                Required
              </span>
            </label>
          </div>
        </div>

        <div class="border-t border-gray-200 bg-gray-50 px-3 py-2">
          <div class="flex items-center justify-between text-xs text-gray-500">
            <span>{{ visibleCount }} of {{ columns.length }} visible</span>
            <button
              v-if="hasChanges"
              type="button"
              class="font-medium text-primary-600 hover:text-primary-700"
              @click="reset"
            >
              Reset
            </button>
          </div>
        </div>
      </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, type PropType } from 'vue'
import { useAnchoredPosition } from '../../Primitives/useAnchoredPosition'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import { getTeleportTarget } from '../../Primitives/teleportTarget'

interface ToggleColumn {
  key: string
  label?: string
  toggleable?: boolean
}

const props = defineProps({
  columns: {
    type: Array as PropType<ToggleColumn[]>,
    required: true,
  },
  modelValue: {
    type: Array as PropType<string[]>,
    default: () => [],
  },
  defaultVisible: {
    type: Array as PropType<string[]>,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

// State
const isOpen = ref(false)
const triggerRef = ref<HTMLElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const visibleColumns = ref([...props.modelValue])
const teleportTarget = getTeleportTarget()

// Flipping, viewport clamping and following the trigger through scrolls used to
// be hand-rolled here against getBoundingClientRect().
const { floatingStyles } = useAnchoredPosition(triggerRef, dropdownRef, isOpen, {
  placement: 'bottom-end',
})

useDismissableLayer(isOpen, {
  elements: () => [triggerRef.value, dropdownRef.value],
  onDismiss: () => { isOpen.value = false },
})

function toggleDropdown() {
  isOpen.value = !isOpen.value
}

const defaultVisibleColumns = ref<string[]>([])

// Computed
const visibleCount = computed(() => visibleColumns.value.length)

const hiddenCount = computed(() => {
  return props.columns.length - visibleCount.value
})

const hasChanges = computed(() => {
  if (defaultVisibleColumns.value.length === 0) return false
  return JSON.stringify([...visibleColumns.value].sort()) !==
         JSON.stringify([...defaultVisibleColumns.value].sort())
})

// Functions
function isVisible(column: ToggleColumn) {
  return visibleColumns.value.includes(column.key)
}

function canToggle(column: ToggleColumn) {
  // Can't toggle if it's the last visible column
  if (visibleColumns.value.length === 1 && isVisible(column)) {
    return false
  }
  // Can't toggle if column is marked as toggleable: false
  return column.toggleable !== false
}

function toggleColumn(column: ToggleColumn) {
  if (!canToggle(column)) return

  const index = visibleColumns.value.indexOf(column.key)
  if (index > -1) {
    visibleColumns.value.splice(index, 1)
  } else {
    // Add column in its original position
    const columnKeys = props.columns.map(c => c.key)
    const newVisible = columnKeys.filter(key =>
      visibleColumns.value.includes(key) || key === column.key
    )
    visibleColumns.value = newVisible
  }

  emit('update:modelValue', visibleColumns.value)
}

function showAll() {
  visibleColumns.value = props.columns.map(c => c.key)
  emit('update:modelValue', visibleColumns.value)
}

function reset() {
  visibleColumns.value = [...defaultVisibleColumns.value]
  emit('update:modelValue', visibleColumns.value)
}

// Initialize
onMounted(() => {
  // Set default visible columns
  if (props.defaultVisible.length > 0) {
    defaultVisibleColumns.value = [...props.defaultVisible]
  } else if (props.modelValue.length > 0) {
    defaultVisibleColumns.value = [...props.modelValue]
  } else {
    defaultVisibleColumns.value = props.columns.map(c => c.key)
  }

  // If no model value provided, use all columns
  if (visibleColumns.value.length === 0) {
    visibleColumns.value = props.columns.map(c => c.key)
    emit('update:modelValue', visibleColumns.value)
  }
})

// Watch for external changes
watch(
  () => props.modelValue,
  (newValue) => {
    visibleColumns.value = [...newValue]
  },
  { deep: true }
)
</script>
