<template>
  <div class="ui-ternary-filter">
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1.5">
      {{ label }}
    </label>
    <select
      :value="modelValue"
      @input="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
      class="ui-input block w-full"
    >
      <option value="">{{ placeholder }}</option>
      <option :value="trueValue">{{ trueLabel }}</option>
      <option :value="falseValue">{{ falseLabel }}</option>
    </select>
  </div>
</template>

<script setup lang="ts">
defineProps({
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'All',
  },
  modelValue: {
    type: [String, Number, Boolean, null],
    default: '',
  },
  trueLabel: {
    type: String,
    default: 'Yes',
  },
  trueValue: {
    type: [String, Number, Boolean],
    default: true,
  },
  falseLabel: {
    type: String,
    default: 'No',
  },
  falseValue: {
    type: [String, Number, Boolean],
    default: false,
  },
})

defineEmits(['update:modelValue'])
</script>
