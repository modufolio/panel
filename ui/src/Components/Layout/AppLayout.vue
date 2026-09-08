<template>
  <div class="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-950">
    <!-- Dropdown Portal Container -->
    <div id="dropdown" />

    <!-- Sidebar -->
    <Sidebar
      v-model:collapsed="sidebarCollapsed"
      :items="navigationItems"
      class="hidden md:flex"
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
        :show-search="showSearch || globalSearch"
        :show-notifications="showNotifications"
        :notification-count="notificationCount"
        :impersonation="impersonation"
        @toggle-mobile-menu="mobileMenuOpen = !mobileMenuOpen"
        @open-search="openSearch"
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
    <GlobalSearchDialog v-if="globalSearch" :show="searchOpen" @close="searchOpen = false" />
    <Toast position="bottom-right" />
  </div>
</template>

<script setup lang="ts">
import type { MenuItem } from '../../types/menu'
import { ref, onMounted, onUnmounted, provide, watch, type PropType } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { SidebarCollapsedKey } from '../../injectionKeys'
import Sidebar from './Sidebar.vue'
import type { SidebarEntry } from './Sidebar.vue'
import TopNavigation from './TopNavigation.vue'
import Toast from '../../Components/Notifications/Toast.vue'
import GlobalSearchDialog from '../Search/GlobalSearchDialog.vue'
import { useToast } from '../../Components/Notifications/useToast'
import { showToast, type PageToast } from '../../Components/Notifications/pageToasts'
import { notifyHttpError, notifyNetworkError, notifyPrefetchedError } from '../../Components/Notifications/httpErrors'

const props = defineProps({
  // Navigation Items
  navigationItems: {
    type: Array as PropType<SidebarEntry[]>,
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
  /**
   * The panel's own search across resources: the top-bar button and ⌘K /
   * Ctrl+K open it. Off, the button emits `open-search` for the app's own.
   */
  globalSearch: {
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

const emit = defineEmits(['open-search', 'open-notifications'])

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

/** Server flash messages, surfaced through DefaultProps as `$page.props.flash`. */
interface FlashProps {
  success?: string | null
  error?: string | null
}

const page = usePage()
const toast = useToast()

/**
 * Server messages, the way the host sends them.
 *
 * A host on the current contract carries them on the page's own `flash`
 * key, the Inertia 3 way: `flash.toasts` is a list of {type, message} the
 * server flashed for this response. Each is shown once and then cleared
 * through the router, so a partial reload that merges flash cannot replay
 * it; the client drops the whole key on the next visit anyway.
 *
 * A host still sharing only a `flash` prop (one success, one error) keeps
 * the old path below: show a message when it changes, and forget it after a
 * moment so the same text can be flashed again later.
 */
watch(
  () => (page.flash as { toasts?: PageToast[] } | undefined)?.toasts,
  (toasts) => {
    if (!Array.isArray(toasts) || toasts.length === 0) return

    for (const entry of toasts) showToast(entry)

    router.flash('toasts', [])
  },
  { immediate: true }
)

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
  () => page.props.flash as FlashProps | undefined,
  (flash) => {
    // A host on the page-level flash carries the same messages there.
    if (Array.isArray((page.flash as { toasts?: unknown } | undefined)?.toasts)) return

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

// Links and drawer neighbours are prefetched; a completed write through the
// router (a form, a delete, a bulk action) makes those snapshots stale.
const stopFlushingPrefetched = router.on('finish', (event) => {
  const visit = event.detail.visit
  if (visit.completed && visit.method !== 'get') {
    router.flushAll()
  }
})

// A response Inertia cannot use — an expired session's 419, a 500 page, a
// 403 — becomes the sentence configured for its status instead of the raw
// error modal; a status nobody mapped keeps the modal. A request that never
// got a status says so too.
const stopInvalidResponses = router.on('httpException', (event) => {
  if (notifyHttpError(event.detail.response.status)) {
    event.preventDefault()
  }
})

// A prefetch that fails never reaches `httpException` — Inertia keeps it for
// the click that replays it. Say so now instead; the event is not cancelable.
const stopFailedPrefetches = router.on('prefetched', (event) => {
  notifyPrefetchedError(event.detail.response?.status)
})

const searchOpen = ref(false)

function openSearch(): void {
  if (props.globalSearch) {
    searchOpen.value = true
  } else {
    emit('open-search')
  }
}

function onSearchShortcut(event: KeyboardEvent): void {
  if (!props.globalSearch) return
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    searchOpen.value = true
  }
}

onMounted(() => document.addEventListener('keydown', onSearchShortcut))
onUnmounted(() => document.removeEventListener('keydown', onSearchShortcut))

const stopExceptions = router.on('networkError', (event) => {
  notifyNetworkError()
  event.preventDefault()
})

onUnmounted(() => {
  stopFlushingPrefetched()
  stopInvalidResponses()
  stopFailedPrefetches()
  stopExceptions()
})
</script>
