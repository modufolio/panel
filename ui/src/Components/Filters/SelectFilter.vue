<template>
  <div class="ui-select-filter">
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1.5">
      {{ label }}
    </label>
    <select
      :value="modelValue"
      @input="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
      class="ui-input block w-full"
    >
      <option value="">{{ placeholder }}</option>
      <option
        v-for="option in options"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>
    <!--
      The panel's rule is that a bound it imposes is visible. When the server
      capped the option list, say so rather than letting the dropdown end
      early and look complete.
    -->
    <p v-if="optionsTruncated" class="mt-1 text-xs text-gray-500">
      Showing the first {{ options.length }} — search the table to narrow further.
    </p>
  </div>
</template>

<script setup lang="ts">
import { type PropType } from 'vue'

interface FilterOption {
  value: string | number
  label: string
}

defineProps({
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Select an option',
  },
  modelValue: {
    type: [String, Number],
    default: '',
  },
  options: {
    type: Array as PropType<FilterOption[]>,
    required: true,
  },
  /** Server capped the list; the remainder is not reachable from this dropdown. */
  optionsTruncated: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:modelValue'])
</script>
