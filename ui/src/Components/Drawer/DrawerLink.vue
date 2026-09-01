<template>
  <a
    :href="href"
    :class="[
      'ui-drawer-link group inline-flex items-center gap-1.5 text-sm font-medium transition-colors cursor-pointer focus:outline-none',
      colorClasses,
    ]"
    @click.prevent="navigate"
  >
    <slot />
    <!-- Default trailing arrow icon -->
    <svg
      v-if="showArrow"
      class="h-4 w-4 shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      stroke-width="1.5"
      stroke="currentColor"
      aria-hidden="true"
    >
      <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
    </svg>
  </a>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import { visitDrawer } from './visitDrawer'

const props = defineProps({
  href: {
    type: String,
    required: true,
  },
  /**
   * Inertia `only` prop — limits which props the server returns.
   * Defaults to ['stack'] so only the drawer stack data reloads.
   */
  only: {
    type: Array as () => string[],
    default: () => ['stack'],
  },
  preserveState: {
    type: Boolean,
    default: true,
  },
  preserveScroll: {
    type: Boolean,
    default: true,
  },
  color: {
    type: String,
    default: 'primary',
    validator: (value: string) => ['primary', 'gray', 'danger', 'success'].includes(value),
  },
  showArrow: {
    type: Boolean,
    default: true,
  },
  /**
   * Query parameters to preserve in the navigation URL.
   * These help the backend maintain list context for navigation.
   */
  queryParams: {
    type: Object as PropType<Record<string, unknown>>,
    default: () => ({}),
  },
})

function navigate(): void {
  visitDrawer(props.href, {
    queryParams: props.queryParams,
    only: props.only,
    preserveState: props.preserveState,
    preserveScroll: props.preserveScroll,
  })
}

const colorClasses = computed(() => {
  const colors: Record<string, string> = {
    primary: 'text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300',
    gray: 'text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100',
    danger: 'text-danger-600 hover:text-danger-700 dark:text-danger-400 dark:hover:text-danger-300',
    success: 'text-success-600 hover:text-success-700 dark:text-success-400 dark:hover:text-success-300',
  }
  return colors[props.color]
})
</script>
