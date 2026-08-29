<template>
  <div class="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-950">
    <!-- Dropdown Portal Container -->
    <div id="dropdown" />

    <!-- Sidebar -->
    <Sidebar
      v-model:collapsed="sidebarCollapsed"
      :items="navigationItems"
      :class="[
        'hidden md:flex',
        sidebarCollapsed ? 'w-24' : 'w-96'
      ]"
    />

    <!-- Mobile Sidebar Overlay -->
    <Transition
      enter-active-class="transition-opacity duration-500"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-500"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="mobileMenuOpen"
        class="fixed inset-0 z-40 bg-gray-950/50 md:hidden dark:bg-gray-950/75"
        @click="mobileMenuOpen = false"
      />
    </Transition>

    <!-- Mobile Sidebar -->
    <Transition
      enter-active-class="transition-transform duration-300"
      enter-from-class="-translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-300"
      leave-from-class="translate-x-0"
      leave-to-class="-translate-x-full"
    >
      <div
        v-if="mobileMenuOpen"
        class="fixed inset-y-0 left-0 z-50 w-96 shadow-xl md:hidden"
      >
        <Sidebar
          :items="navigationItems"
          :collapsed="false"
          class="h-full"
        />
      </div>
    </Transition>

    <!-- Main Content Area -->
    <div class="flex flex-col flex-1 overflow-hidden">
      <!-- Top Navigation -->
      <TopNavigation
        :user-name="userName"
        :user-first-name="userFirstName"
        :user-last-name="userLastName"
        :user-email="userEmail"
        :user-avatar="userAvatar"
        :menu-items="userMenuItems"
        :show-search="showSearch"
        :show-notifications="showNotifications"
        :notification-count="notificationCount"
        :impersonation="impersonation"
        @toggle-mobile-menu="mobileMenuOpen = !mobileMenuOpen"
        @open-search="$emit('open-search')"
        @open-notifications="$emit('open-notifications')"
      >
        <template v-if="$slots.breadcrumbs" #breadcrumbs>
          <slot name="breadcrumbs" />
        </template>
        <template v-if="$slots.actions" #actions>
          <slot name="actions" />
        </template>
      </TopNavigation>

      <!-- Main Content -->
      <main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-950">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 md:py-8">
          <!-- Page Content -->
          <slot />
        </div>
      </main>

      <!-- Footer (optional) -->
      <footer v-if="$slots.footer" class="border-t ring-1 ring-gray-950/5 bg-white dark:bg-gray-900 dark:ring-white/10">
        <slot name="footer" />
      </footer>
    </div>

    <!-- App-supplied dev tooling (debug bar, profiler, …) -->
    <slot name="debug" />

    <!-- Toast Notifications -->
    <Toast position="bottom-right" />
  </div>
</template>

<script setup lang="ts">
import type { MenuItem } from '../../types/menu'
import { ref, onMounted, onUnmounted, provide, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { SidebarCollapsedKey } from '../../injectionKeys'
import Sidebar from './Sidebar.vue'
import TopNavigation from './TopNavigation.vue'
import Toast from '../../Components/Notifications/Toast.vue'
import { useToast } from '../../Components/Notifications/useToast'

const props = defineProps({
  // Navigation Items
  navigationItems: {
    type: Array,
    default: () => []
  },

  // User Info
  userName: {
    type: String,
    required: true
  },
  userFirstName: {
    type: String,
    required: true
  },
  userLastName: {
    type: String,
    default: ''
  },
  userEmail: {
    type: String,
    default: ''
  },
  userAvatar: {
    type: String,
    default: ''
  },

  // User Menu Items
  userMenuItems: {
    type: Array as () => MenuItem[],
    default: (): MenuItem[] => []
  },

  // Feature Flags
  showSearch: {
    type: Boolean,
    default: false
  },
  showNotifications: {
    type: Boolean,
    default: false
  },
  notificationCount: {
    type: Number,
    default: 0
  },

  // Sidebar Config
  initialSidebarCollapsed: {
    type: Boolean,
    default: false
  },

  // Impersonation info
  impersonation: {
    type: Object,
    default: null
  },


})

defineEmits(['open-search', 'open-notifications'])

// State
const sidebarCollapsed = ref(props.initialSidebarCollapsed)
const mobileMenuOpen = ref(false)

// Bridge server-side flash messages (Symfony FlashBag, surfaced via
// DefaultProps as $page.props.flash) into toast notifications. Centralized
// here so every panel page gets it for free — including redirects that land
// on an arbitrary referring page (e.g. AlbumController::redirectBack()).
//
// A page with deferred props (DeferredProp::make(), e.g. Media/Index.vue's
// childAlbums/breadcrumb) triggers a second, near-simultaneous background
// request right after the initial load. If that request's session read
// races the initial request's session write-back, both can see the same
// not-yet-cleared flash message — so the exact same value can arrive twice
// within milliseconds. Suppress a repeat of the same message inside a short
// window, but let it show again for a genuine later repeat (e.g. saving two
// different records in a row that both say "Updated successfully").
const DUPLICATE_FLASH_WINDOW_MS = 2000
const page = usePage()
const toast = useToast()
let lastFlashSuccess: string | null = null
let lastFlashError: string | null = null
let clearLastFlashTimer: ReturnType<typeof setTimeout> | null = null

function scheduleClearLastFlash() {
  if (clearLastFlashTimer) clearTimeout(clearLastFlashTimer)
  clearLastFlashTimer = setTimeout(() => {
    lastFlashSuccess = null
    lastFlashError = null
  }, DUPLICATE_FLASH_WINDOW_MS)
}

watch(
  () => page.props.flash,
  (flash: any) => {
    if (flash?.success && flash.success !== lastFlashSuccess) {
      toast.success(flash.success)
    }
    lastFlashSuccess = flash?.success ?? null

    if (flash?.error && flash.error !== lastFlashError) {
      toast.error(flash.error)
    }
    lastFlashError = flash?.error ?? null

    scheduleClearLastFlash()
  },
  { deep: true, immediate: false }
)

onUnmounted(() => {
  if (clearLastFlashTimer) clearTimeout(clearLastFlashTimer)
})

// Share collapsed state with deeply nested components (e.g. AlbumSidebar via teleport)
provide(SidebarCollapsedKey, sidebarCollapsed)

// Load sidebar state from localStorage on mount
onMounted(() => {
  const savedCollapsed = localStorage.getItem('sidebar-collapsed')
  if (savedCollapsed !== null) {
    sidebarCollapsed.value = savedCollapsed === '1'
  }
})
</script>
