<template>
  <label
    class="ui-label block text-sm font-medium text-label"
    :class="[required && 'after:content-[\'*\'] after:ml-0.5 after:text-danger', labelClass]"
    :for="forId"
  >
    <slot>{{ text }}</slot>
  </label>
</template>

<script setup lang="ts">
defineProps({
  text: {
    type: String,
    default: '',
  },
  forId: {
    type: String,
    default: undefined,
  },
  required: {
    type: Boolean,
    default: false,
  },
  labelClass: {
    type: String,
    default: '',
  },
})
</script>
