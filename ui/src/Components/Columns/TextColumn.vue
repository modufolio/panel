<template>
  <div class="ui-text-column flex items-center gap-2">
    <!-- Icon (optional) -->
    <component
      v-if="icon"
      :is="icon"
      class="ui-text-column-icon shrink-0"
      :class="iconClass"
    />

    <!-- Content -->
    <div class="ui-text-column-content" :class="contentClass">
      <!-- Label -->
      <div
        v-if="label"
        class="ui-text-column-label"
        :class="labelClass"
      >
        {{ label }}
      </div>

      <!-- Description (optional) -->
      <div
        v-if="description"
        class="ui-text-column-description text-xs text-gray-500 mt-0.5"
      >
        {{ description }}
      </div>
    </div>

    <!-- Suffix (optional) -->
    <span v-if="suffix" class="ui-text-column-suffix text-gray-500 text-sm">
      {{ suffix }}
    </span>
  </div>
</template>

<script setup lang="ts">
defineProps({
  label: {
    type: [String, Number],
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  icon: {
    type: [Object, String],
    default: null,
  },
  iconClass: {
    type: String,
    default: 'w-5 h-5',
  },
  suffix: {
    type: String,
    default: '',
  },
  contentClass: {
    type: String,
    default: '',
  },
  labelClass: {
    type: String,
    default: '',
  },
})
</script>