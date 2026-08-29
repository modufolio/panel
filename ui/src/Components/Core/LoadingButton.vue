<template>
  <button class="flex items-center" :disabled="loading" v-bind="$attrs">
    <span v-if="loading" class="btn-spinner mr-2" />
    <slot />
  </button>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  loading?: boolean
}>(), {
  loading: false,
})
</script>
