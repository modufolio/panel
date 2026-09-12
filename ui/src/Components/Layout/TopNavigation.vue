<template>
  <div class="border-b border-line">
    <!-- Main Navigation Bar -->
    <div class="flex items-center justify-between h-16 px-4 bg-surface text-ink shrink-0">

      <div class="flex items-center space-x-4">
      <!-- Mobile Menu Toggle -->
      <button
        v-if="showMobileToggle"
        @click="$emit('toggle-mobile-menu')"
        class="md:hidden p-2 text-ink-3 hover:bg-hover focus-visible:bg-hover rounded-lg transition-all duration-75"
      >
        <icon name="menu" class="w-6 h-6 fill-current" />
      </button>

      <slot name="breadcrumbs" />
    </div>

    <!-- Right Section: Actions & User Menu -->
    <div class="flex items-center space-x-4">
      <!-- Custom Actions Slot -->
      <div v-if="$slots.actions" class="flex items-center space-x-2">
        <slot name="actions" />
      </div>

      <!-- Global Search (optional) -->
      <button
        v-if="showSearch"
        @click="$emit('open-search')"
        class="p-2 text-ink-3 hover:bg-hover focus-visible:bg-hover rounded-lg transition-all duration-75"
        title="Search (Cmd+K)"
      >
        <icon name="search" class="w-5 h-5 fill-current" />
      </button>

      <!-- Notifications (optional) -->
      <button
        v-if="showNotifications"
        @click="$emit('open-notifications')"
        class="relative p-2 text-ink-3 hover:bg-hover focus-visible:bg-hover rounded-lg transition-all duration-75"
        title="Notifications"
      >
        <icon name="bell" class="w-5 h-5 fill-current" />
        <span
          v-if="notificationCount > 0"
          class="absolute top-1 right-1 flex items-center justify-center w-4 h-4 text-xs font-bold bg-danger-fill text-danger-on-fill rounded-full"
        >
          {{ notificationCount > 9 ? '9+' : notificationCount }}
        </span>
      </button>

      <!-- Theme -->
      <ThemeSwitcher v-if="showThemeSwitcher" />

      <!-- User Menu Dropdown -->
      <dropdown placement="bottom-end">
        <template #default>
          <div class="group flex items-center px-3 py-2 space-x-2 cursor-pointer select-none hover:bg-hover focus-visible:bg-hover rounded-lg transition-all duration-75">
            <!-- User Avatar (if provided) -->
            <div class="relative">
              <div
                v-if="userAvatar"
                class="w-8 h-8 bg-surface-sunken rounded-full overflow-hidden"
              >
                <img :src="userAvatar" :alt="userName" class="w-full h-full object-cover" />
              </div>
              <div
                v-else
                class="flex items-center justify-center w-8 h-8 bg-primary-fill dark:bg-white text-primary-on-fill rounded-full text-sm font-medium"
              >
                {{ userInitials }}
              </div>
              <!-- Impersonation indicator dot -->
              <span
                v-if="impersonation?.is_impersonating"
                class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-warning-fill border-2 border-surface rounded-full"
              />
            </div>

            <!-- User Name -->
            <div class="hidden md:block text-sm font-medium text-ink">
              <span>{{ userFirstName }}</span>
              <span class="hidden lg:inline">&nbsp;{{ userLastName }}</span>
            </div>

            <!-- Chevron -->
            <icon
              name="chevron-down"
              class="w-4 h-4 transition-colors duration-75"
            />
          </div>
        </template>

        <template #dropdown>
          <div class="mt-2 py-2 w-56 text-sm bg-surface-raised text-ink rounded-lg shadow-xl ring-1 ring-hairline">
            <!-- User Info Header -->
            <div class="px-4 py-3 border-b border-line">
              <div class="font-medium text-ink">{{ userName }}</div>
              <div v-if="userEmail" class="text-xs text-ink-3 truncate">
                {{ userEmail }}
              </div>
            </div>

            <!-- Impersonation Notice -->
            <div
              v-if="impersonation?.is_impersonating"
              class="px-4 py-3 border-b border-line bg-warning-surface"
            >
              <div class="text-xs text-warning-on-surface">
                Viewing as <strong>{{ userName }}</strong>
              </div>
              <div class="text-xs text-warning-on-surface/80 mt-0.5">
                Logged in as {{ impersonation.original_user.first_name }} {{ impersonation.original_user.last_name }}
              </div>
              <Link
                :href="panelUrl('/users/switch/exit')"
                method="post"
                as="button"
                class="mt-2 w-full px-3 py-1.5 text-xs font-medium text-center bg-warning-fill text-warning-on-fill hover:opacity-90 rounded-md transition-opacity duration-75"
              >
                Exit Switch User
              </Link>
            </div>

            <!-- Menu Items -->
            <div class="py-2">
              <template v-for="(item, index) in menuItems" :key="index">
                <!-- Action-based item (opens dialog, etc.) -->
                <button
                  v-if="item.action"
                  type="button"
                  @click="item.action()"
                  :class="[
                    'flex items-center justify-between w-full px-4 py-2 text-left text-ink hover:bg-hover focus-visible:bg-hover transition-all duration-75',
                    item.divider && 'border-t border-line mt-2 pt-2'
                  ]"
                >
                  <div class="flex items-center">
                    <icon v-if="item.icon" :name="item.icon" class="w-5 h-5 mr-3" />
                    <span>{{ item.label }}</span>
                  </div>
                </button>

                <!-- Link-based item (navigation) -->
                <Link
                  v-else
                  :href="item.href"
                  :method="item.method"
                  :prefetch="!item.method"
                  cache-for="10s"
                  :data="item.method === 'post' && item.href === panelUrl('/logout') ? { _csrf_token: String($page.props.logout_csrf ?? '') } : undefined"
                  :as="item.method ? 'button' : 'a'"
                  :class="[
                    'flex items-center justify-between w-full px-4 py-2 text-left text-ink hover:bg-hover focus-visible:bg-hover transition-all duration-75',
                    item.divider && 'border-t border-line mt-2 pt-2'
                  ]"
                >
                  <div class="flex items-center">
                    <icon
                      v-if="item.icon"
                      :name="item.icon"
                      class="w-5 h-5 mr-3"
                    />
                    <span>{{ item.label }}</span>
                  </div>
                  <span
                    v-if="item.badge"
                    :class="[
                      'ml-2 px-2 py-0.5 text-xs font-medium rounded-full',
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
              </template>
            </div>
          </div>
        </template>
      </dropdown>
    </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { panelUrl } from '../../Utils/url'
import Icon from '../../Components/Core/Icon.vue'
import Dropdown from '../../Components/Core/Dropdown.vue'
import ThemeSwitcher from './ThemeSwitcher.vue'

import type { MenuItem } from '../../types/menu'

const props = defineProps({
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
  menuItems: {
    type: Array as () => MenuItem[],
    default: (): MenuItem[] => [
      { label: 'My Profile', href: panelUrl('/profile'), icon: 'users' },
      { label: 'Settings', href: panelUrl('/settings'), icon: 'office' },
      { label: 'Logout', href: panelUrl('/logout'), icon: 'trash', divider: true, method: 'post' }
    ]
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
  showMobileToggle: {
    type: Boolean,
    default: true
  },
  /**
   * The light/dark control. On by default — a panel whose theme cannot be
   * changed from its own chrome sends the user to look for a settings page
   * that does not exist. Off for a host that places <ThemeSwitcher> itself.
   */
  showThemeSwitcher: {
    type: Boolean,
    default: true
  },

  // Impersonation info
  impersonation: {
    type: Object,
    default: null
  }
})

defineEmits(['toggle-mobile-menu', 'open-search', 'open-notifications'])

const userInitials = computed(() => {
  const first = props.userFirstName?.[0] || ''
  const last = props.userLastName?.[0] || ''
  return (first + last).toUpperCase() || '?'
})
</script>
