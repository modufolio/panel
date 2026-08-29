<template>
  <nav class="ui-breadcrumbs" aria-label="Breadcrumb">
    <ol class="ui-breadcrumbs-list flex items-center gap-2 text-sm">
      <li
        v-for="(item, index) in items"
        :key="index"
        class="ui-breadcrumb-item flex items-center gap-2"
      >
        <!-- Separator (except for first item) -->
        <svg
          v-if="index > 0"
          class="ui-breadcrumb-separator w-4 h-4 text-gray-400 flex-shrink-0"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.5"
          stroke="currentColor"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>

        <!-- Home Icon (optional for first item) -->
        <svg
          v-if="index === 0 && showHomeIcon"
          class="ui-breadcrumb-home-icon w-4 h-4 flex-shrink-0"
          :class="isActive(index) ? 'text-gray-900' : 'text-gray-500'"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.5"
          stroke="currentColor"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
        </svg>

        <!-- Breadcrumb Link or Text -->
        <component
          :is="getComponent(item, index)"
          :href="item.url || item.href"
          :class="getBreadcrumbClass(index)"
          class="ui-breadcrumb-link"
          :aria-current="isActive(index) ? 'page' : null"
        >
          <template v-if="item.icon && !showHomeIcon">
            <component :is="item.icon" class="w-4 h-4 inline-block mr-1" />
          </template>
          {{ item.label || item.name }}
        </component>
      </li>
    </ol>
  </nav>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { PropType } from 'vue'

const props = defineProps({
  items: {
    type: Array as PropType<any[]>,
    required: true,
    validator: (items: unknown) => {
      return (items as any[]).every((item: any) => item.label || item.name)
    }
  },
  showHomeIcon: {
    type: Boolean,
    default: true,
  },
  activeClass: {
    type: String,
    default: 'text-gray-900 font-medium',
  },
  inactiveClass: {
    type: String,
    default: 'text-gray-500 hover:text-gray-700 transition-colors',
  },
})

function isActive(index: number) {
  return index === props.items.length - 1
}

function getComponent(item: any, index: number) {
  // Last item or item without URL is just text
  if (isActive(index) || (!item.url && !item.href)) {
    return 'span'
  }

  // Use Inertia Link for internal navigation
  return Link
}

function getBreadcrumbClass(index: number) {
  if (isActive(index)) {
    return props.activeClass
  }
  return props.inactiveClass
}
</script>