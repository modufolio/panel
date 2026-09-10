<template>
  <div class="inline-flex items-center gap-2">
    <button
      type="button"
      role="switch"
      :aria-checked="isChecked"
      :disabled="disabled || loading"
      class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-50"
      :class="[
        isChecked
          ? checkedClasses
          : 'bg-line-strong focus:ring-focus',
        loading ? 'cursor-wait' : '',
      ]"
      @click="handleToggle"
    >
      <!--
        The knob rides on the track, so on it takes the checked role's
        `-on-fill` — the token that exists precisely to say what is legible on
        that fill. `gray` is why: its fill is a light grey in the dark theme,
        where a fixed near-white knob would vanish into its own track.
      -->
      <span
        aria-hidden="true"
        class="pointer-events-none inline-block h-4 w-4 transform rounded-full shadow ring-0 transition duration-200 ease-in-out"
        :class="[
          isChecked ? `translate-x-4 ${knobClasses}` : 'translate-x-0 bg-control-knob',
        ]"
      />
    </button>

    <!-- Optional Label -->
    <span
      v-if="showLabel"
      class="text-sm text-ink-2"
    >
      {{ isChecked ? onLabel : offLabel }}
    </span>

    <!-- Loading indicator -->
    <span
      v-if="loading"
      class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-solid border-primary border-r-transparent"
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
  primary: 'bg-primary-fill focus:ring-primary',
  success: 'bg-success-fill focus:ring-success',
  danger: 'bg-danger-fill focus:ring-danger',
  warning: 'bg-warning-fill focus:ring-warning',
  info: 'bg-info-fill focus:ring-info',
  gray: 'bg-gray-fill focus:ring-gray',
}

const knobClassMap: Record<string, string> = {
  primary: 'bg-primary-on-fill',
  success: 'bg-success-on-fill',
  danger: 'bg-danger-on-fill',
  warning: 'bg-warning-on-fill',
  info: 'bg-info-on-fill',
  gray: 'bg-gray-on-fill',
}

const checkedClasses = computed(() => colorClassMap[props.color] ?? colorClassMap.primary)

const knobClasses = computed(() => knobClassMap[props.color] ?? knobClassMap.primary)

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
