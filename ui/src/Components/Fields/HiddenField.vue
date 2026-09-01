<script setup lang="ts">
/**
 * Stored but never shown: keeps its value in the form model without
 * rendering an affordance. The server's HiddenType declares it; importer
 * bookkeeping and computed values ride along in submits untouched.
 */
defineProps({
  modelValue: { type: null, default: null },
  label: { type: String, default: '' },
})

defineEmits<{ 'update:model-value': [value: unknown] }>()
</script>

<template>
  <input type="hidden" :value="modelValue == null ? '' : String(modelValue)">
</template>
