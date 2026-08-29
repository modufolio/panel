<template>
  <div class="inline-flex items-center gap-2">
    <button
      type="button"
      role="switch"
      :aria-checked="isChecked"
      :disabled="disabled || loading"
      class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
      :class="[
        isChecked
          ? checkedClasses
          : 'bg-gray-200 focus:ring-gray-400',
        loading ? 'cursor-wait' : '',
      ]"
      @click="handleToggle"
    >
      <span
        aria-hidden="true"
        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
        :class="[
          isChecked ? 'translate-x-4' : 'translate-x-0',
        ]"
      />
    </button>

    <!-- Optional Label -->
    <span
      v-if="showLabel"
      class="text-sm text-gray-700"
    >
      {{ isChecked ? onLabel : offLabel }}
    </span>

    <!-- Loading indicator -->
    <span
      v-if="loading"
      class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-solid border-primary-600 border-r-transparent"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps({
  value: {
    type: [Boolean, Number, String],
    default: false,
  },
  record: {
    type: Object,
    required: true,
  },
  column: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    default: 'primary',
    validator: (value: unknown) =>
      ['primary', 'success', 'danger', 'warning', 'info', 'gray'].includes(value as string),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  showLabel: {
    type: Boolean,
    default: false,
  },
  onLabel: {
    type: String,
    default: 'On',
  },
  offLabel: {
    type: String,
    default: 'Off',
  },
  // Callback function for updates
  onUpdate: {
    type: Function,
    default: null,
  },
})

const emit = defineEmits(['update'])

const loading = ref(false)

const colorClassMap: Record<string, string> = {
  primary: 'bg-primary-600 focus:ring-primary-600',
  success: 'bg-success-600 focus:ring-success-600',
  danger: 'bg-danger-600 focus:ring-danger-600',
  warning: 'bg-warning-600 focus:ring-warning-600',
  info: 'bg-info-600 focus:ring-info-600',
  gray: 'bg-gray-600 focus:ring-gray-600',
}

const checkedClasses = computed(() => colorClassMap[props.color] ?? colorClassMap.primary)

const isChecked = computed(() => {
  // Handle different types of truthy values
  if (typeof props.value === 'boolean') {
    return props.value
  }
  if (typeof props.value === 'number') {
    return props.value === 1
  }
  if (typeof props.value === 'string') {
    return props.value === '1' || props.value.toLowerCase() === 'true'
  }
  return false
})

async function handleToggle() {
  // Prevent toggle if disabled
  if (props.disabled || loading.value) {
    return
  }

  const newValue = !isChecked.value
  loading.value = true

  try {
    if (props.onUpdate) {
      // Call custom update handler
      await props.onUpdate(props.record, props.column, newValue)
    }

    if (!props.onUpdate) {
    // Emitted only when no `onUpdate` prop was supplied. Vue treats a prop
    // named `onUpdate` as a listener for a declared `update` emit, so doing
    // both called the page's save handler twice — the second time with the
    // event object in place of (record, column, value).
      emit('update', {
        record: props.record,
        column: props.column,
        oldValue: isChecked.value,
        newValue: newValue,
      })
    }
  } catch (error) {
    console.error('Error updating toggle column:', error)
    // Don't update the UI on error - the value will stay as is
  } finally {
    loading.value = false
  }
}
</script>
