<template>
  <div class="border-b">
    <!-- Main Navigation Bar -->
    <div class="flex items-center justify-between h-16 px-4 bg-white shrink-0 dark:bg-gray-900 dark:ring-white/10">

      <div class="flex items-center space-x-4">
      <!-- Mobile Menu Toggle -->
      <button
        v-if="showMobileToggle"
        @click="$emit('toggle-mobile-menu')"
        class="md:hidden p-2 text-gray-400 hover:bg-gray-50 focus-visible:bg-gray-50 rounded-lg transition-all duration-75 dark:text-gray-500 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
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
        class="p-2 text-gray-400 hover:bg-gray-50 focus-visible:bg-gray-50 rounded-lg transition-all duration-75 dark:text-gray-500 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
        title="Search (Cmd+K)"
      >
        <icon name="search" class="w-5 h-5 fill-current" />
      </button>

      <!-- Notifications (optional) -->
      <button
        v-if="showNotifications"
        @click="$emit('open-notifications')"
        class="relative p-2 text-gray-400 hover:bg-gray-50 focus-visible:bg-gray-50 rounded-lg transition-all duration-75 dark:text-gray-500 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
        title="Notifications"
      >
        <icon name="bell" class="w-5 h-5 fill-current" />
        <span
          v-if="notificationCount > 0"
          class="absolute top-1 right-1 flex items-center justify-center w-4 h-4 text-xs font-bold text-white bg-danger-500 rounded-full dark:bg-danger-600"
        >
          {{ notificationCount > 9 ? '9+' : notificationCount }}
        </span>
      </button>

      <!-- User Menu Dropdown -->
      <dropdown placement="bottom-end">
        <template #default>
          <div class="group flex items-center px-3 py-2 space-x-2 cursor-pointer select-none hover:bg-gray-50 focus-visible:bg-gray-50 rounded-lg transition-all duration-75 dark:hover:bg-white/5 dark:focus-visible:bg-white/5">
            <!-- User Avatar (if provided) -->
            <div class="relative">
              <div
                v-if="userAvatar"
                class="w-8 h-8 bg-gray-200 rounded-full overflow-hidden dark:bg-gray-700"
              >
                <img :src="userAvatar" :alt="userName" class="w-full h-full object-cover" />
              </div>
              <div
                v-else
                class="flex items-center justify-center w-8 h-8 bg-primary-600 text-white rounded-full text-sm font-medium dark:bg-primary-500"
              >
                {{ userInitials }}
              </div>
              <!-- Impersonation indicator dot -->
              <span
                v-if="impersonation?.is_impersonating"
                class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-warning-500 border-2 border-white rounded-full dark:border-gray-900"
              />
            </div>

            <!-- User Name -->
            <div class="hidden md:block text-sm font-medium text-gray-700 dark:text-gray-200">
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
          <div class="mt-2 py-2 w-56 text-sm bg-white rounded-lg shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <!-- User Info Header -->
            <div class="px-4 py-3 border-b ring-1 ring-gray-950/5 dark:ring-white/10">
              <div class="font-medium text-gray-950 dark:text-white">{{ userName }}</div>
              <div v-if="userEmail" class="text-xs text-gray-500 truncate dark:text-gray-400">
                {{ userEmail }}
              </div>
            </div>

            <!-- Impersonation Notice -->
            <div
              v-if="impersonation?.is_impersonating"
              class="px-4 py-3 border-b ring-1 ring-gray-950/5 bg-warning-50 dark:bg-warning-900/20 dark:ring-white/10"
            >
              <div class="text-xs text-warning-700 dark:text-warning-300">
                Viewing as <strong>{{ userName }}</strong>
              </div>
              <div class="text-xs text-warning-600 dark:text-warning-400 mt-0.5">
                Logged in as {{ impersonation.original_user.first_name }} {{ impersonation.original_user.last_name }}
              </div>
              <Link
                :href="panelUrl('/users/switch/exit')"
                method="post"
                as="button"
                class="mt-2 w-full px-3 py-1.5 text-xs font-medium text-center text-warning-800 bg-warning-100 hover:bg-warning-200 rounded-md transition-colors duration-75 dark:text-warning-200 dark:bg-warning-800 dark:hover:bg-warning-700"
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
                    'flex items-center justify-between w-full px-4 py-2 text-left text-gray-700 hover:bg-gray-50 focus-visible:bg-gray-50 transition-all duration-75 dark:text-gray-200 dark:hover:bg-white/5 dark:focus-visible:bg-white/5',
                    item.divider && 'border-t ring-1 ring-gray-950/5 mt-2 pt-2 dark:ring-white/10'
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
                  :data="item.method === 'post' && item.href === panelUrl('/logout') ? { _csrf_token: String($page.props.logout_csrf ?? '') } : undefined"
                  :as="item.method ? 'button' : 'a'"
                  :class="[
                    'flex items-center justify-between w-full px-4 py-2 text-left text-gray-700 hover:bg-gray-50 focus-visible:bg-gray-50 transition-all duration-75 dark:text-gray-200 dark:hover:bg-white/5 dark:focus-visible:bg-white/5',
                    item.divider && 'border-t ring-1 ring-gray-950/5 mt-2 pt-2 dark:ring-white/10'
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
                      item.badgeColor === 'primary' && 'bg-primary-100 text-primary-800 dark:bg-primary-500/20 dark:text-primary-400',
                      item.badgeColor === 'success' && 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-400',
                      item.badgeColor === 'danger' && 'bg-danger-100 text-danger-800 dark:bg-danger-500/20 dark:text-danger-400',
                      item.badgeColor === 'warning' && 'bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-400',
                      !item.badgeColor && 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
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
