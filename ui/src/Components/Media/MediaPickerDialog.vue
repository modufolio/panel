<template>
  <Dialog :is-open="isOpen" title="Choose image" width="4xl" @close="emit('close')">

    <!-- Filter bar -->
    <div class="mb-4 space-y-2">
      <!-- Active filter rows -->
      <div v-for="row in filters" :key="row.id" class="flex items-center gap-2">
        <!-- Field selector -->
        <select
          v-model="row.field"
          class="ui-input ui-input-auto py-1.5 pl-2 pr-7"
          @change="onFieldChange(row)"
        >
          <option value="album">Album</option>
          <option value="title">Title</option>
          <option value="filename">Filename</option>
          <option value="favorite">Favorite</option>
        </select>

        <!-- Operator selector (hidden for boolean fields) -->
        <select
          v-if="row.field !== 'favorite'"
          v-model="row.op"
          class="ui-input ui-input-auto py-1.5 pl-2 pr-7"
          @change="onFilterChange"
        >
          <template v-if="row.field === 'album'">
            <option value="is_in">is in</option>
          </template>
          <template v-else-if="row.field === 'title'">
            <option value="contains">contains</option>
            <option value="starts_with">starts with</option>
            <option value="ends_with">ends with</option>
            <option value="is">is</option>
          </template>
          <template v-else>
            <option value="contains">contains</option>
            <option value="starts_with">starts with</option>
          </template>
        </select>

        <!-- Favorite: label only, no value needed -->
        <span v-if="row.field === 'favorite'" class="flex-1 text-sm text-gray-500 italic">is marked as favorite</span>

        <!-- Value: album multi-select -->
        <div v-if="row.field === 'album'" class="relative flex-1" :data-album-row="row.id">
          <div
            class="flex flex-wrap gap-1 min-h-[34px] w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-sm cursor-pointer"
            @click="toggleAlbumDropdown(row.id)"
          >
            <span v-if="(row.value as string[]).length === 0" class="text-gray-400 self-center">Select albums…</span>
            <template v-else>
              <span
                v-for="id in (row.value as string[])"
                :key="id"
                class="inline-flex items-center gap-1 bg-primary-100 text-primary-700 rounded px-1.5 py-0.5 text-xs"
              >
                {{ albumLabel(id) }}
                <button type="button" class="hover:text-primary-900" @click.stop="removeAlbumFromRow(row, id)">×</button>
              </span>
            </template>
          </div>
          <!-- Album dropdown -->
          <div
            v-if="albumDropdownOpen === row.id"
            class="absolute z-20 mt-1 w-full max-h-52 overflow-y-auto rounded-lg bg-white shadow-xl ring-1 ring-black/10 py-1"
          >
            <div v-if="albumsLoading" class="px-3 py-2 text-xs text-gray-500">Loading…</div>
            <div v-else-if="albumList.length === 0" class="px-3 py-2 text-xs text-gray-500">No albums found</div>
            <label
              v-for="album in albumList"
              :key="album.id"
              class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer"
            >
              <input
                type="checkbox"
                :checked="(row.value as string[]).includes(album.id)"
                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                @change="toggleAlbum(row, album.id)"
              />
              <span class="text-sm text-gray-700">{{ album.title }}</span>
            </label>
          </div>
        </div>

        <!-- Value: text input -->
        <input
          v-else-if="row.field === 'title' || row.field === 'filename'"
          v-model="(row as TextFilterRow).value"
          type="text"
          placeholder="Value…"
          class="ui-input ui-input-auto flex-1 py-1.5 px-2"
          @input="onTextInput"
        />

        <!-- Remove row -->
        <button
          type="button"
          class="text-gray-400 hover:text-gray-600 text-lg leading-none px-1"
          @click="removeFilter(row.id)"
        >×</button>
      </div>

      <!-- Add filter button -->
      <button
        type="button"
        class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700 font-medium"
        @click="addFilter"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Add Filter
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <svg class="animate-spin h-6 w-6 text-primary-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <div v-else class="space-y-4">
      <!-- Grid -->
      <div v-if="images.length > 0" class="grid grid-cols-4 sm:grid-cols-5 gap-2 max-h-[50vh] overflow-y-auto pr-1">
        <button
          v-for="image in images"
          :key="image.id"
          type="button"
          class="relative aspect-square rounded-sm overflow-hidden border-2 border-transparent hover:border-primary-500 focus:border-primary-500 focus:outline-none transition-colors bg-gray-100"
          @click="selectImage(image)"
        >
          <img
            :src="image.thumbnail_url"
            :alt="image.alt_text || image.original_filename"
            class="w-full h-full object-cover"
          />
        </button>
      </div>

      <!-- Empty -->
      <div v-else class="text-center py-12 text-gray-500">
        <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        No images found
      </div>

      <!-- Pagination -->
      <div v-if="meta.total > 0" class="pt-2 border-t border-gray-100">
        <TablePagination
          :current-page="meta.page"
          :last-page="meta.pages"
          :total="meta.total"
          :per-page="perPage"
          :from="paginationFrom"
          :to="paginationTo"
          :per-page-options="[20, 40, 60]"
          @goto="loadPage"
          @update:per-page="updatePerPage"
        />
      </div>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { panelUrl } from '../../Utils/url'
import Dialog from '../../Components/Dialogs/Dialog.vue'
import TablePagination from '../../Components/Table/TablePagination.vue'
import { getMediaEndpoints } from './mediaEndpoints'
import { apiFetch } from '../../Utils/apiFetch'

interface MediaItem {
  id: string
  url: string
  thumbnail_url: string
  original_filename: string
  alt_text: string
  caption: string
}

interface Meta {
  total: number
  page: number
  limit: number
  pages: number
}

interface AlbumOption {
  id: string
  title: string
}

interface AlbumFilterRow {
  id: number
  field: 'album'
  op: 'is_in'
  value: string[]
}

interface TextFilterRow {
  id: number
  field: 'title' | 'filename'
  op: string
  value: string
}

interface FavoriteFilterRow {
  id: number
  field: 'favorite'
  op: ''
  value: null
}

type FilterRow = AlbumFilterRow | TextFilterRow | FavoriteFilterRow

const props = defineProps<{ isOpen: boolean }>()

const emit = defineEmits<{
  'close': []
  'select': [image: MediaItem]
}>()

const loading = ref(false)
const images = ref<MediaItem[]>([])
const meta = ref<Meta>({ total: 0, page: 1, limit: 40, pages: 1 })
const perPage = ref(40)

const paginationFrom = computed(() =>
  meta.value.total === 0 ? 0 : (meta.value.page - 1) * meta.value.limit + 1,
)
const paginationTo = computed(() =>
  Math.min(meta.value.page * meta.value.limit, meta.value.total),
)

// Filters
const filters = ref<FilterRow[]>([])
let filterIdCounter = 0

// Albums
const albumList = ref<AlbumOption[]>([])
const albumsLoading = ref(false)
const albumsFetched = ref(false)
const albumDropdownOpen = ref<number | null>(null)

// Debounce handle
let debounceTimer: ReturnType<typeof setTimeout> | null = null

function albumLabel(id: string): string {
  return albumList.value.find((a) => a.id === id)?.title ?? String(id)
}

function addFilter() {
  filters.value.push({
    id: ++filterIdCounter,
    field: 'title',
    op: 'contains',
    value: '',
  })
}

function removeFilter(id: number) {
  filters.value = filters.value.filter((r) => r.id !== id)
  if (albumDropdownOpen.value === id) albumDropdownOpen.value = null
  onFilterChange()
}

function onFieldChange(row: FilterRow) {
  if (row.field === 'album') {
    ;(row as AlbumFilterRow).op = 'is_in'
    ;(row as AlbumFilterRow).value = []
    loadAlbums()
  } else if (row.field === 'favorite') {
    ;(row as FavoriteFilterRow).op = ''
    ;(row as FavoriteFilterRow).value = null
  } else {
    ;(row as TextFilterRow).op = 'contains'
    ;(row as TextFilterRow).value = ''
  }
  onFilterChange()
}

function onFilterChange() {
  albumDropdownOpen.value = null
  loadPage(1)
}

function onTextInput() {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => loadPage(1), 300)
}

function toggleAlbumDropdown(rowId: number) {
  if (albumDropdownOpen.value === rowId) {
    albumDropdownOpen.value = null
  } else {
    albumDropdownOpen.value = rowId
    loadAlbums()
  }
}

function toggleAlbum(row: FilterRow, albumId: string) {
  if (row.field !== 'album') return
  const arr = (row as AlbumFilterRow).value
  const idx = arr.indexOf(albumId)
  if (idx > -1) {
    arr.splice(idx, 1)
  } else {
    arr.push(albumId)
  }
  loadPage(1)
}

function removeAlbumFromRow(row: FilterRow, albumId: string) {
  if (row.field !== 'album') return
  const arr = (row as AlbumFilterRow).value
  const idx = arr.indexOf(albumId)
  if (idx > -1) arr.splice(idx, 1)
  loadPage(1)
}

async function loadAlbums() {
  if (albumsFetched.value || albumsLoading.value) return
  albumsLoading.value = true
  try {
    albumList.value = await apiFetch(panelUrl(getMediaEndpoints().albums))
    albumsFetched.value = true
  } catch {
    albumList.value = []
  } finally {
    albumsLoading.value = false
  }
}

function updatePerPage(size: number) {
  perPage.value = size
  loadPage(1)
}

function buildQueryString(page: number): string {
  const qs = new URLSearchParams()
  qs.set('page[number]', String(page))
  qs.set('page[size]', String(perPage.value))

  for (const row of filters.value) {
    if (row.field === 'album') {
      for (const id of (row as AlbumFilterRow).value) {
        qs.append('filter[album_ids][]', String(id))
      }
    } else if (row.field === 'title') {
      const val = (row as TextFilterRow).value.trim()
      if (val) {
        qs.set('filter[title]', val)
        qs.set('filter[title_op]', row.op)
      }
    } else if (row.field === 'filename') {
      const val = (row as TextFilterRow).value.trim()
      if (val) {
        qs.set('filter[filename]', val)
        qs.set('filter[filename_op]', row.op)
      }
    } else if (row.field === 'favorite') {
      qs.set('filter[favorite]', '1')
    }
  }

  return qs.toString()
}

async function loadPage(page: number) {
  loading.value = true
  try {
    const json: any = await apiFetch(panelUrl(`${getMediaEndpoints().list}?${buildQueryString(page)}`))
    images.value = json.data ?? []
    meta.value = json.meta ?? meta.value
  } catch {
    images.value = []
  } finally {
    loading.value = false
  }
}

function selectImage(image: MediaItem) {
  emit('select', image)
  emit('close')
}

// Close album dropdown when clicking outside its container
function handleClickOutside(e: MouseEvent) {
  if (albumDropdownOpen.value !== null) {
    const target = e.target as HTMLElement
    if (!target.closest(`[data-album-row="${albumDropdownOpen.value}"]`)) {
      albumDropdownOpen.value = null
    }
  }
}

watch(
  () => props.isOpen,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('click', handleClickOutside)
      if (images.value.length === 0) loadPage(1)
    } else {
      document.removeEventListener('click', handleClickOutside)
      albumDropdownOpen.value = null
    }
  },
)
</script>
