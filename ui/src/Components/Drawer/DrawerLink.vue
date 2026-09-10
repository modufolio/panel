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
  // Role tokens carry their own per-theme value, so the hover is the role's own
  // on-surface foreground — darker in light, lighter in dark — rather than a
  // second palette step with a dark-mode twin. Opacity is avoided here: the
  // trailing arrow fades in on the same hover.
  const colors: Record<string, string> = {
    primary: 'text-primary hover:text-primary-on-surface',
    gray: 'text-ink-2 hover:text-ink',
    danger: 'text-danger hover:text-danger-on-surface',
    success: 'text-success hover:text-success-on-surface',
  }
  return colors[props.color]
})
</script>
