<template>
  <!--
    Read-only rows keep the glyph but lose the button, so a row nobody may
    edit never offers a control that would be refused.
  -->
  <span
    v-if="readOnly"
    class="ui-toggle-icon-column inline-flex h-7 w-7 items-center justify-center"
    :class="colorClasses"
    :title="stateLabel"
  >
    <Icon :name="iconName" class="h-5 w-5" />
    <span class="sr-only">{{ stateLabel }}</span>
  </span>

  <button
    v-else
    type="button"
    class="ui-toggle-icon-column inline-flex h-7 w-7 items-center justify-center rounded-full transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50"
    :class="[colorClasses, loading ? 'cursor-wait' : '']"
    :disabled="disabled || loading"
    :aria-pressed="isOn"
    :aria-label="stateLabel"
    :title="stateLabel"
    @click="handleToggle"
  >
    <Icon :name="iconName" class="h-5 w-5" />
  </button>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import Icon from '../Core/Icon.vue'

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
  /** Icon name shown while the value is true. */
  onIcon: {
    type: String,
    default: 'check-circle',
  },
  /** Icon name shown while the value is false. */
  offIcon: {
    type: String,
    default: 'x-circle',
  },
  onColor: {
    type: String,
    default: 'success',
  },
  offColor: {
    type: String,
    default: 'gray',
  },
  onLabel: {
    type: String,
    default: 'On',
  },
  offLabel: {
    type: String,
    default: 'Off',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  /** Render the icon without a button — the row may be read but not changed. */
  readOnly: {
    type: Boolean,
    default: false,
  },
  // Save callback, supplied by the page through `cellHandlers`.
  onUpdate: {
    type: Function,
    default: null,
  },
})

const emit = defineEmits(['update'])

const loading = ref(false)

const isOn = computed(() => {
  if (typeof props.value === 'boolean') return props.value
  if (typeof props.value === 'number') return props.value !== 0
  if (typeof props.value === 'string') {
    const lower = props.value.toLowerCase()

    return lower === 'true' || lower === '1' || lower === 'yes'
  }

  return false
})

const iconName = computed(() => (isOn.value ? props.onIcon : props.offIcon))

const stateLabel = computed(() => (isOn.value ? props.onLabel : props.offLabel))

const colorMap: Record<string, string> = {
  primary: 'text-primary-600',
  success: 'text-success-600',
  danger: 'text-danger-600',
  warning: 'text-warning-600',
  info: 'text-info-600',
  gray: 'text-gray-400',
}

const colorClasses = computed(() => {
  const color = isOn.value ? props.onColor : props.offColor

  return colorMap[color] ?? colorMap.gray
})

async function handleToggle(): Promise<void> {
  if (props.disabled || loading.value) return

  const newValue = !isOn.value
  loading.value = true

  try {
    if (props.onUpdate) {
      await props.onUpdate(props.record, props.column, newValue)
    } else {
      // Same one-or-the-other rule as ToggleColumn: Vue reads an `onUpdate`
      // prop as a listener for the declared `update` emit, so doing both
      // called the page's save handler twice.
      emit('update', {
        record: props.record,
        column: props.column,
        oldValue: isOn.value,
        newValue,
      })
    }
  } catch (error) {
    // The value stays as it was — the row is re-read from the server on a
    // successful save, and a failed one must not claim otherwise.
    console.error('Error updating toggle icon column:', error)
  } finally {
    loading.value = false
  }
}
</script>
