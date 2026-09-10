<template>
  <component :is="iconComponent" v-if="iconComponent" ref="iconRef" />
</template>

<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import {
  ArchiveBoxIcon,
  ArrowRightOnRectangleIcon,
  ArrowTopRightOnSquareIcon,
  ArrowUpTrayIcon,
  Bars3Icon,
  BellIcon,
  BookmarkIcon,
  BookOpenIcon,
  BuildingOfficeIcon,
  CalendarIcon,
  ChartBarIcon,
  ChevronDownIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronUpIcon,
  ClipboardDocumentListIcon,
  ClockIcon,
  Cog6ToothIcon,
  ComputerDesktopIcon,
  CubeIcon,
  DocumentTextIcon,
  EnvelopeIcon,
  EllipsisHorizontalIcon,
  EllipsisVerticalIcon,
  ExclamationCircleIcon,
  EyeIcon,
  EyeSlashIcon,
  FolderIcon,
  FolderOpenIcon,
  GiftIcon,
  HeartIcon,
  HomeIcon,
  InformationCircleIcon,
  LinkIcon,
  LockClosedIcon,
  MagnifyingGlassIcon,
  MoonIcon,
  PencilIcon,
  PhotoIcon,
  PlusIcon,
  PrinterIcon,
  RectangleGroupIcon,
  RectangleStackIcon,
  ShieldCheckIcon,
  ShoppingBagIcon,
  ShoppingCartIcon,
  Square2StackIcon,
  StarIcon,
  SparklesIcon,
  SunIcon,
  SwatchIcon,
  TagIcon,
  TrashIcon,
  UserCircleIcon,
  UserGroupIcon,
  UsersIcon,
  ViewColumnsIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
} from '@heroicons/vue/24/solid'

import SitemapIcon from './Icons/SitemapIcon.vue'
import DocumentLinesIcon from './Icons/DocumentLinesIcon.vue'
import { getCustomIcon } from './iconRegistry'

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
  ariaHidden: {
    type: [Boolean, String],
    default: true,
  },
})

const iconMap: Record<string, unknown> = {
  // Navigation
  'chevron-down': ChevronDownIcon,
  'chevron-right': ChevronRightIcon,
  'chevron-left': ChevronLeftIcon,
  'chevron-up': ChevronUpIcon,
  'menu': Bars3Icon,
  'ellipsis-h': EllipsisHorizontalIcon,
  'ellipsis-v': EllipsisVerticalIcon,

  // Actions
  'plus': PlusIcon,
  'edit': PencilIcon,
  'trash': TrashIcon,
  'bookmark': BookmarkIcon,
  'eye': EyeIcon,
  'eye-off': EyeSlashIcon,
  'search': MagnifyingGlassIcon,
  'upload': ArrowUpTrayIcon,
  'link': LinkIcon,
  'external-link': ArrowTopRightOnSquareIcon,
  'logout': ArrowRightOnRectangleIcon,
  'x': XMarkIcon,

  // Status
  'check-circle': CheckCircleIcon,
  'x-circle': XCircleIcon,
  'exclamation': ExclamationCircleIcon,
  'exclamation-triangle': ExclamationTriangleIcon,
  'info': InformationCircleIcon,

  // Navigation / App
  'dashboard': HomeIcon,
  'home': HomeIcon,
  'calendar': CalendarIcon,
  'clock': ClockIcon,
  'bell': BellIcon,
  'settings': Cog6ToothIcon,
  'shield': ShieldCheckIcon,

  // Theme. Built in rather than app-registered: ThemeSwitcher ships with the
  // panel, so its three icons cannot depend on a consumer calling registerIcons.
  'sun': SunIcon,
  'moon': MoonIcon,
  'monitor': ComputerDesktopIcon,

  // Users
  'user': UserCircleIcon,
  'users': UsersIcon,
  'user-group': UserGroupIcon,
  'lock-closed': LockClosedIcon,

  // Business
  'office': BuildingOfficeIcon,
  'building': BuildingOfficeIcon,
  'printer': PrinterIcon,

  // E-commerce
  'shopping-cart': ShoppingCartIcon,
  'shopping-bag': ShoppingBagIcon,
  'cube': CubeIcon,
  'tag': TagIcon,

  // Navigation / menus
  'bars-3': Bars3Icon,
  'navigation': SitemapIcon,
  'sitemap': SitemapIcon,

  // Documents
  'document': DocumentTextIcon,
  'document-lines': DocumentLinesIcon,
  'clipboard': ClipboardDocumentListIcon,
  'mail': EnvelopeIcon,
  'folder': FolderIcon,
  'folder-open': FolderOpenIcon,
  'archive': ArchiveBoxIcon,
  'book': BookOpenIcon,

  // Media / Photo CMS
  'photo': PhotoIcon,
  'photos': Square2StackIcon,
  'album': RectangleStackIcon,
  'collection': RectangleGroupIcon,
  'sparkles': SparklesIcon,
  'swatch': SwatchIcon,
  'star': StarIcon,
  'gift': GiftIcon,
  'heart': HeartIcon,

  // Stats / Charts
  'chart': ChartBarIcon,
  'kanban': ViewColumnsIcon,
  'layout-kanban': ViewColumnsIcon,
}

const iconComponent = computed(() => getCustomIcon(props.name) ?? iconMap[props.name] ?? null)

const iconRef = ref<SVGElement | null>(null)

const applyAriaHidden = (): void => {
  const el = iconRef.value
  if (el?.setAttribute) {
    el.setAttribute('aria-hidden', String(props.ariaHidden))
  }
}

onMounted(applyAriaHidden)
watch(() => props.ariaHidden, applyAriaHidden)
</script>
