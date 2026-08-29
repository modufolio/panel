<template>
  <div class="ui-drawer-tabs">
    <!-- Tab bar: bleeds to the drawer body's edges so the rule spans it fully -->
    <div class="-mx-6 -mt-6 mb-6 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-center gap-6 px-6">
        <nav
          ref="tablistRef"
          class="flex min-w-0 flex-1 items-center gap-6 overflow-x-auto"
          role="tablist"
          :aria-label="ariaLabel"
          @keydown="onKeydown"
        >
          <button
            v-for="tab in resolvedTabs"
            :id="tabId(tab.key)"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="tab.key === activeKey"
            :aria-controls="panelId(tab.key)"
            :tabindex="tab.key === activeKey ? 0 : -1"
            :disabled="tab.disabled"
            :data-tab="tab.key"
            class="group flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
            :class="tab.key === activeKey
              ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200'"
            @click="select(tab)"
          >
            <slot :name="`tab-${tab.key}`" :tab="tab" :active="tab.key === activeKey">
              <Icon v-if="tab.icon" :name="tab.icon" class="h-4 w-4" aria-hidden="true" />
              <span>{{ tab.label }}</span>
              <span
                v-if="tab.badge !== undefined && tab.badge !== null && tab.badge !== ''"
                class="ml-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="tab.key === activeKey
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300'
                  : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
              >{{ tab.badge }}</span>
            </slot>
          </button>
        </nav>

        <!-- Trailing affordances (JSON:API link, row actions, …) -->
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2 self-center">
          <slot name="actions" :active-key="activeKey" />
        </div>
      </div>
    </div>

    <!-- Panels: only the active one is rendered, unless the tab opts into keep-alive -->
    <template v-for="tab in resolvedTabs" :key="tab.key">
      <div
        v-if="tab.key === activeKey || (keepAlive && mounted.has(tab.key))"
        v-show="tab.key === activeKey"
        :id="panelId(tab.key)"
        role="tabpanel"
        :aria-labelledby="tabId(tab.key)"
        tabindex="0"
        class="focus:outline-none"
      >
        <slot :name="tab.key" :tab="tab" />
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Icon from '../Core/Icon.vue'
import { useId } from '../../Primitives/useId'
import type { DrawerTab } from './drawerTabs'

const props = withDefaults(defineProps<{
  tabs: Array<DrawerTab | string>
  modelValue?: string
  ariaLabel?: string
  /** Keep visited panels mounted so their local state survives tab switches. */
  keepAlive?: boolean
}>(), {
  modelValue: undefined,
  ariaLabel: 'Drawer sections',
  keepAlive: false,
})

const emit = defineEmits<{
  'update:modelValue': [key: string]
  change: [key: string]
}>()

const uid = useId(undefined, 'drawer-tabs')
const tablistRef = ref<HTMLElement | null>(null)

/** Ids wiring each tab to its panel; scoped by instance so stacked drawers don't collide. */
const tabId = (key: string): string => `${uid}-tab-${key}`
const panelId = (key: string): string => `${uid}-panel-${key}`

/** Accepts either full tab objects or bare keys (`['details', 'files']`). */
const resolvedTabs = computed<DrawerTab[]>(() =>
  props.tabs.map((tab) =>
    typeof tab === 'string'
      ? { key: tab, label: tab.charAt(0).toUpperCase() + tab.slice(1) }
      : tab,
  ),
)

const selectableTabs = computed(() => resolvedTabs.value.filter((tab) => !tab.disabled))

/** Uncontrolled fallback, so the component is usable without v-model. */
const internalKey = ref<string | undefined>(props.modelValue)

/**
 * Falls back to the first selectable tab whenever the bound key is missing or
 * points at a tab that no longer exists — a tab list driven by permissions or
 * record state can lose the active tab between renders.
 */
const activeKey = computed<string>(() => {
  const candidate = props.modelValue ?? internalKey.value
  const exists = candidate !== undefined && selectableTabs.value.some((tab) => tab.key === candidate)

  return exists ? candidate! : (selectableTabs.value[0]?.key ?? '')
})

const mounted = ref(new Set<string>())
watch(activeKey, (key) => {
  if (key) mounted.value.add(key)
}, { immediate: true })

function select(tab: DrawerTab): void {
  if (tab.disabled || tab.key === activeKey.value) return

  internalKey.value = tab.key
  emit('update:modelValue', tab.key)
  emit('change', tab.key)
}

function focusTab(key: string): void {
  tablistRef.value
    ?.querySelector<HTMLElement>(`[data-tab="${CSS.escape(key)}"]`)
    ?.focus()
}

/** Roving-tabindex keyboard model (WAI-ARIA tabs pattern). */
function onKeydown(event: KeyboardEvent): void {
  const tabs = selectableTabs.value
  if (tabs.length === 0) return

  const current = tabs.findIndex((tab) => tab.key === activeKey.value)
  let next = -1

  switch (event.key) {
    case 'ArrowRight': next = (current + 1) % tabs.length; break
    case 'ArrowLeft': next = (current - 1 + tabs.length) % tabs.length; break
    case 'Home': next = 0; break
    case 'End': next = tabs.length - 1; break
    default: return
  }

  // Stop here so the drawer's own ArrowUp/Down record navigation and any
  // scroll container don't also react to the same press.
  event.preventDefault()
  event.stopPropagation()

  select(tabs[next])
  focusTab(tabs[next].key)
}
</script>
