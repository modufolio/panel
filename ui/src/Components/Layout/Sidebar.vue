<template>
  <div
    :class="[
      'flex flex-col bg-surface text-ink transition-all duration-300 ease-in-out',
      isCollapsed ? 'w-16' : 'w-96',
      'shrink-0 overflow-hidden'
    ]"
  >
    <!-- Logo Section -->
    <div class="flex items-center justify-between border-b border-line bg-surface px-6 h-16">
      <Link :href="getPanelBaseUrl()" class="flex items-center shrink-0">
        <!-- Consumers supply their own mark; the default is a neutral square. -->
        <slot name="logo" :collapsed="isCollapsed">
          <svg
            class="fill-ink"
            width="28"
            height="28"
            viewBox="0 0 28 28"
            aria-hidden="true"
          ><rect x="2" y="2" width="24" height="24" rx="6" /></svg>
        </slot>
      </Link>
    </div>

    <!-- Navigation Items + Media Library Sidebar (integrated, always visible) -->
    <div class="flex-1 overflow-y-auto">
    <nav class="px-3 py-4">
      <div
        v-for="(group, gIndex) in groupedNav"
        :key="gIndex"
      >
        <!-- Group Header (collapsible) -->
        <div
          v-if="group.name"
          :class="['mb-1 mt-4', isCollapsed ? 'border-t border-line-strong pt-4' : '']"
        >
          <button
            v-if="!isCollapsed"
            @click="toggleGroup(group.name)"
            class="w-full flex items-center justify-between px-3 mb-1 text-xs font-semibold text-label uppercase tracking-wider hover:text-ink transition-colors"
          >
            <span>{{ group.name }}</span>
            <svg
              :class="['w-3 h-3 transition-transform duration-200', isGroupOpen(group.name) ? 'rotate-90' : '']"
              fill="currentColor" viewBox="0 0 20 20"
            >
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>

        <!-- Group Items -->
        <div
          v-show="isCollapsed || !group.name || isGroupOpen(group.name)"
          class="space-y-1"
        >
          <div
            v-for="(item, index) in group.items"
            :key="index"
            class="mb-1"
          >
            <!-- Regular Navigation Item -->
            <div v-if="!item.children">
              <Link
                :href="item.href"
                prefetch
                cache-for="10s"
                :class="[
                  'group flex h-9 items-center rounded-lg px-3 transition-colors duration-75',
                  isActive(item.href)
                    ? 'bg-pressed'
                    : 'hover:bg-hover focus-visible:bg-hover'
                ]"
                :title="isCollapsed ? item.label : ''"
              >
                <icon
                  :name="item.icon"
                  :class="[
                    'nav-icon w-4 h-4 shrink-0 transition-colors duration-75',
                    isCollapsed ? '' : 'mr-3'
                  ]"
                />
                <span
                  v-if="!isCollapsed"
                  class="text-sm font-medium truncate text-ink"
                >
                  {{ item.label }}
                </span>
                <span
                  v-if="item.badge && !isCollapsed"
                  :class="[
                    'ml-auto px-2 py-0.5 text-xs font-medium rounded-full',
                    item.badgeColor === 'primary' && 'bg-primary-surface text-primary-on-surface',
                    item.badgeColor === 'success' && 'bg-success-surface text-success-on-surface',
                    item.badgeColor === 'danger' && 'bg-danger-surface text-danger-on-surface',
                    item.badgeColor === 'warning' && 'bg-warning-surface text-warning-on-surface',
                    !item.badgeColor && 'bg-gray-surface text-gray-on-surface'
                  ]"
                >
                  {{ item.badge }}
                </span>
              </Link>
            </div>

            <!-- Nested Navigation Item -->
            <div v-else>
              <button
                @click="toggleSubmenu(`${gIndex}-${index}`)"
                :class="[
                  'group w-full flex h-9 items-center rounded-lg px-3 transition-colors duration-75',
                  hasActiveChild(item)
                    ? 'bg-pressed'
                    : 'hover:bg-hover focus-visible:bg-hover'
                ]"
                :title="isCollapsed ? item.label : ''"
              >
                <icon
                  :name="item.icon"
                  :class="[
                    'nav-icon w-4 h-4 shrink-0 transition-colors duration-75',
                    isCollapsed ? '' : 'mr-3'
                  ]"
                />
                <span
                  v-if="!isCollapsed"
                  class="flex-1 text-left text-sm font-medium truncate text-ink"
                >
                  {{ item.label }}
                </span>
                <icon
                  v-if="!isCollapsed"
                  name="chevron-down"
                  :class="[
                    'w-4 h-4 ml-2 transition-transform duration-200',
                    openSubmenus[`${gIndex}-${index}`] ? 'transform rotate-180' : '',
                  ]"
                />
              </button>

              <!-- Submenu Items -->
              <div
                v-if="!isCollapsed"
                v-show="openSubmenus[`${gIndex}-${index}`]"
                class="mt-1 ml-4 space-y-1 border-l-2 border-line-strong pl-4"
              >
                <Link
                  v-for="(child, childIndex) in item.children"
                  :key="childIndex"
                  :href="child.href"
                  prefetch
                  cache-for="10s"
                  :class="[
                    'group flex items-center px-2 py-2 rounded-lg text-sm transition-all duration-75',
                    isActive(child.href)
                      ? 'bg-pressed'
                      : 'hover:bg-hover focus-visible:bg-hover'
                  ]"
                >
                  <icon
                    v-if="child.icon"
                    :name="child.icon"
                    class="nav-icon w-4 h-4 mr-3 shrink-0 transition-colors duration-75"
                  />
                  <span class="truncate font-medium text-ink">
                    {{ child.label }}
                  </span>
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Media Library sidebar portal (always present; AlbumSidebar teleports here on /library pages) -->
    <div id="sidebar-nav-portal" />
    </div>

    <!-- Toggle Button -->
    <div class="border-t border-line ring-1 ring-hairline p-3">
      <button
        @click="toggleSidebar"
        class="group w-full flex h-9 items-center rounded-lg px-3 text-ink hover:bg-hover focus-visible:bg-hover transition-colors duration-75"
        :title="isCollapsed ? (isCollapsed ? 'Expand sidebar' : 'Collapse sidebar') : ''"
      >
        <icon
          :name="isCollapsed ? 'chevron-right' : 'chevron-left'"
          class="nav-icon w-4 h-4 shrink-0 transition-colors duration-75"
          :class="isCollapsed ? '' : 'mr-3'"
        />
        <span v-if="!isCollapsed" class="text-sm font-medium text-ink">
          Collapse
        </span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import type { PropType } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { getPanelBaseUrl } from '../../Utils/url'
import Icon from '../../Components/Core/Icon.vue'

const GROUPS_KEY = 'sidebar-groups'

/** A submenu link; shown without an icon when it has none. */
export interface SidebarChildItem {
  label: string
  href: string
  icon?: string
}

/** A top-level navigation link, optionally carrying a submenu. */
export interface SidebarItem extends SidebarChildItem {
  /** Always drawn — it is all that remains of the item when the sidebar is collapsed. */
  icon: string
  badge?: string | number
  badgeColor?: 'primary' | 'success' | 'danger' | 'warning'
  children?: SidebarChildItem[]
}

/** A heading; the items that follow it belong to that group until the next one. */
export interface SidebarGroupHeading {
  group: string
}

export type SidebarEntry = SidebarItem | SidebarGroupHeading

const props = defineProps({
  items: {
    type: Array as PropType<SidebarEntry[]>,
    default: () => []
  },
  collapsed: {
    type: Boolean,
    default: false
  },
  width: {
    type: String,
    default: '256px'
  },
  collapsedWidth: {
    type: String,
    default: '64px'
  }
})

const emit = defineEmits(['update:collapsed'])

const page = usePage()
const isCollapsed = ref(props.collapsed)
const openSubmenus = reactive<Record<string, boolean>>({})

const navigationItems = computed(() => props.items)

// ── Group collapse state ──────────────────────────────────────────
const groupOpenState = reactive<Record<string, boolean>>({})

const loadGroupStates = () => {
  try {
    const saved = localStorage.getItem(GROUPS_KEY)
    if (saved) Object.assign(groupOpenState, JSON.parse(saved))
  } catch {}
}
loadGroupStates()

const isGroupOpen = (name: string): boolean => groupOpenState[name] ?? true

const toggleGroup = (name: string) => {
  groupOpenState[name] = !isGroupOpen(name)
  try { localStorage.setItem(GROUPS_KEY, JSON.stringify({ ...groupOpenState })) } catch {}
}

// Flatten nav array into grouped structure
const groupedNav = computed(() => {
  const groups: Array<{ name: string | null; items: SidebarItem[] }> = []
  let current: { name: string | null; items: SidebarItem[] } = { name: null, items: [] }

  for (const item of navigationItems.value) {
    if ('group' in item) {
      if (current.items.length > 0 || current.name !== null) groups.push(current)
      current = { name: item.group, items: [] }
    } else {
      current.items.push(item)
    }
  }
  if (current.items.length > 0 || current.name !== null) groups.push(current)

  return groups
})

const toggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value
  emit('update:collapsed', isCollapsed.value)

  // Store preference in localStorage
  localStorage.setItem('sidebar-collapsed', isCollapsed.value ? '1' : '0')
}

const toggleSubmenu = (index: string) => {
  if (isCollapsed.value) {
    // If sidebar is collapsed, don't toggle submenu, just expand sidebar
    isCollapsed.value = false
    emit('update:collapsed', false)
    openSubmenus[index] = true
  } else {
    openSubmenus[index] = !openSubmenus[index]
  }
}

const isActive = (href: string) => {
  const currentUrl = new URL(page.url, window.location.origin).pathname
  if (currentUrl === href) return true

  // Only use startsWith for nested routes (3+ segments), not top-level routes like /panel
  const segments = href.split('/').filter(Boolean).length
  if (segments > 1 && currentUrl.startsWith(`${href}/`)) {
    return true
  }

  return false
}

const hasActiveChild = (item: SidebarItem) => {
  if (!item.children) return false
  return item.children.some((child) => isActive(child.href))
}

// Initialize open submenus based on active items
const initializeOpenSubmenus = () => {
  groupedNav.value.forEach((group, gIndex) => {
    group.items.forEach((item, index) => {
      if (item.children && hasActiveChild(item)) {
        openSubmenus[`${gIndex}-${index}`] = true
      }
    })
  })
}

// Initialize on mount
initializeOpenSubmenus()

// Watch for route changes to update open submenus
watch(() => page.url, () => {
  initializeOpenSubmenus()
})

// Load saved preference on mount
const savedCollapsed = localStorage.getItem('sidebar-collapsed')
if (savedCollapsed !== null) {
  isCollapsed.value = savedCollapsed === '1'
  emit('update:collapsed', isCollapsed.value)
}
</script>
