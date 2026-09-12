<template>
  <component :is="iconComponent" v-if="iconComponent" ref="iconRef" />
</template>

<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import {
  ArchiveBoxIcon,
  ArrowRightOnRectangleIcon,
  ArrowTopRightOnSquareIcon,
  ArrowDownIcon,
  ArrowUpIcon,
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
import { pathIcon } from '../../Utils/pathIcon'

// Block-builder glyphs. Kept as raw path data (rather than a heroicon) so the
// builder's toolbar/slash-menu icons don't shift when heroicons' set changes.
const ParagraphIcon = pathIcon('M4 6h16M4 10h16M4 14h16M4 18h7', { strokeWidth: 2 })
const Heading2Icon = pathIcon('M4 6h16M4 12h7', { strokeWidth: 2 })
const Heading3Icon = pathIcon('M4 6h12M4 12h6', { strokeWidth: 2 })
const BlockquoteIcon = pathIcon(
  'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
  { strokeWidth: 2 },
)
const BulletListIcon = pathIcon('M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01', { strokeWidth: 2 })
const CodeBlockIcon = pathIcon('M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', { strokeWidth: 2 })
const ImageBlockIcon = pathIcon(
  'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
  { strokeWidth: 2 },
)

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
  'columns': ViewColumnsIcon,
  'duplicate': Square2StackIcon,
  'arrow-up': ArrowUpIcon,
  'arrow-down': ArrowDownIcon,

  // Block builder
  'paragraph': ParagraphIcon,
  'heading-2': Heading2Icon,
  'heading-3': Heading3Icon,
  'blockquote': BlockquoteIcon,
  'bullet-list': BulletListIcon,
  'ordered-list': BulletListIcon,
  'code-block': CodeBlockIcon,
  'image-block': ImageBlockIcon,
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
