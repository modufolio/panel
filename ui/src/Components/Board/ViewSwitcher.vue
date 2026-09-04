<template>
  <!--
    Nothing to switch between is not a switcher: a resource declaring only the
    table renders no control at all, which is what every listing looked like
    before views existed.
  -->
  <div
    v-if="views.length > 1"
    class="ui-view-switcher inline-flex rounded-lg bg-gray-100 p-0.5"
    role="tablist"
    :aria-label="ariaLabel"
  >
    <button
      v-for="view in views"
      :key="view.key"
      type="button"
      role="tab"
      :aria-selected="view.key === active"
      :title="view.label"
      class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-sm font-medium transition-colors"
      :class="view.key === active
        ? 'bg-white text-gray-900 shadow-sm'
        : 'text-gray-500 hover:text-gray-700'"
      @click="$emit('select', view.key)"
    >
      <Icon v-if="view.icon" :name="view.icon" class="h-4 w-4" />
      <span>{{ view.label }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import Icon from '../Core/Icon.vue'
import type { ResourceViewOption } from './boardTypes'

withDefaults(defineProps<{
  views: ResourceViewOption[]
  active: string
  ariaLabel?: string
}>(), {
  ariaLabel: 'View',
})

defineEmits<{ select: [key: string] }>()
</script>
