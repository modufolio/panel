<template>
  <div class="ui-page-header">
    <!-- Breadcrumbs -->
    <div v-if="$slots.breadcrumbs || breadcrumbs" class="ui-page-breadcrumbs mb-2">
      <slot name="breadcrumbs">
        <Breadcrumbs v-if="breadcrumbs" :items="breadcrumbs" />
      </slot>
    </div>

    <!-- Header Content -->
    <div class="ui-page-header-content">
      <div class="ui-page-header-heading">
        <!-- Icon/Badge -->
        <div v-if="$slots.icon || icon" class="ui-page-header-icon mr-3">
          <slot name="icon">
            <component
              v-if="icon"
              :is="icon"
              class="w-8 h-8 text-gray-700"
            />
          </slot>
        </div>

        <!-- Title and Description -->
        <div class="ui-page-header-text flex-1">
          <h1 class="ui-page-header-title text-2xl font-bold tracking-tight text-gray-900">
            <slot name="title">
              {{ title }}
            </slot>
          </h1>

          <p
            v-if="$slots.description || description"
            class="ui-page-header-description mt-1 text-sm text-gray-500"
          >
            <slot name="description">
              {{ description }}
            </slot>
          </p>
        </div>
      </div>

      <!-- Actions -->
      <div v-if="$slots.actions" class="ui-page-header-actions">
        <slot name="actions" />
      </div>
    </div>

    <!-- Tabs or Additional Content -->
    <div v-if="$slots.tabs" class="ui-page-header-tabs mt-4 border-b border-gray-200">
      <slot name="tabs" />
    </div>
  </div>
</template>

<script setup lang="ts">
import Breadcrumbs from './Breadcrumbs.vue'

defineProps({
  title: {
    type: String,
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
  breadcrumbs: {
    type: Array,
    default: null,
  },
})
</script>

