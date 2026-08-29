<template>
  <div class="ui-section" :class="[...sectionClasses, 'overflow-visible']">
    <!-- Header -->
    <div v-if="$slots.header || heading" class="ui-section-header" :class="[
      { 'flex items-center gap-3': $slots.headerActions },
      headerClasses
    ]">
      <slot name="header">
        <div :class="{ 'flex-1': $slots.headerActions }">
          <h2 v-if="heading" class="text-lg font-semibold text-gray-900">
            {{ heading }}
          </h2>
          <p v-if="description" class="mt-1 text-sm text-gray-600">
            {{ description }}
          </p>
        </div>
        <div v-if="$slots.headerActions" class="flex-shrink-0">
          <slot name="headerActions" />
        </div>
      </slot>
    </div>

    <!-- Content -->
    <div class="ui-section-content" :class="contentClasses">
      <slot />
    </div>

    <!-- Footer -->
    <div v-if="$slots.footer" class="ui-section-footer" :class="footerClasses">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  heading: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  card: {
    type: Boolean,
    default: true,
  },
  aside: {
    type: Boolean,
    default: false,
  },
  collapsible: {
    type: Boolean,
    default: false,
  },
})

const sectionClasses = computed(() => {
  const classes = []

  if (props.card) {
    classes.push('bg-white rounded-lg shadow-sm ring-1 ring-gray-950/5')
  }

  if (props.aside) {
    classes.push('lg:grid lg:grid-cols-3 lg:gap-6')
  }

  return classes
})

const headerClasses = computed(() => {
  const classes = []

  if (props.card) {
    classes.push('px-6 py-4 border-b border-gray-200')
  }

  return classes
})

const contentClasses = computed(() => {
  const classes = []

  if (props.card) {
    classes.push('p-6')
  }

  if (props.aside) {
    classes.push('lg:col-span-2')
  }

  return classes
})

const footerClasses = computed(() => {
  const classes = []

  if (props.card) {
    classes.push('px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl')
  }

  return classes
})
</script>
