<template>
  <div class="inline-block">
    <select
      :value="value"
      :disabled="disabled || loading"
      class="ui-input block"
      :class="[
        loading ? 'cursor-wait' : '',
        getOptionClass(value),
      ]"
      @change="handleChange"
    >
      <option
        v-if="placeholder"
        value=""
        disabled
        selected
        class="text-gray-500"
      >
        {{ placeholder }}
      </option>
      <option
        v-for="option in normalizedOptions"
        :key="option.value"
        :value="option.value"
        :class="option.class"
      >
        {{ option.label }}
      </option>
    </select>

    <!-- Loading indicator -->
    <span
      v-if="loading"
      class="ml-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-solid border-primary-600 border-r-transparent align-middle"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, type PropType } from 'vue'

/** One choice as rendered; a bare string or number is accepted and normalised to this. */
interface SelectOption {
  label: string
  value: string | number
  class?: string
}

const props = defineProps({
  value: {
    type: [String, Number, Boolean],
    default: '',
  },
  record: {
    type: Object,
    required: true,
  },
  column: {
    type: String,
    required: true,
  },
  options: {
    type: Array as PropType<Array<SelectOption | string | number>>,
    required: true,
  },
  placeholder: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  // Callback function for updates
  onUpdate: {
    type: Function,
    default: null,
  },
})

const emit = defineEmits(['update'])

const loading = ref(false)

// Normalize options to { label, value, class? } format
const normalizedOptions = computed(() => {
  return props.options.map((option): SelectOption => {
    if (typeof option === 'string' || typeof option === 'number') {
      return { label: String(option), value: option }
    }
    return option
  })
})

function getOptionClass(value: string | number | boolean): string {
  const option = normalizedOptions.value.find(opt => opt.value === value)
  return option?.class || ''
}

async function handleChange(event: Event): Promise<void> {
  const target = event.target as HTMLSelectElement
  const newValue = target.value

  // Prevent change if disabled
  if (props.disabled || loading.value) {
    return
  }

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
        oldValue: props.value,
        newValue: newValue,
      })
    }
  } catch (error) {
    console.error('Error updating select column:', error)
    // Revert the select value on error
    ;(event.target as HTMLSelectElement).value = String(props.value)
  } finally {
    loading.value = false
  }
}
</script>
