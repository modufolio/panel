<template>
  <Teleport to="body">
    <div
      v-if="uploads.length > 0"
      class="fixed bottom-6 right-6 z-[150] w-80 pointer-events-auto"
    >
      <!-- Panel -->
      <div class="rounded-lg shadow-xl ring-1 ring-hairline bg-surface-raised overflow-hidden">

        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 bg-surface-sunken border-b border-line">
          <button
            type="button"
            class="flex items-center gap-2 hover:bg-hover transition-colors rounded px-2 py-1 -mx-2"
            @click="collapsed = !collapsed"
          >
            <!-- Spinner when any upload is active -->
            <svg
              v-if="hasActiveUploads"
              class="w-4 h-4 text-info animate-spin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <!-- Error icon when any upload failed -->
            <svg
              v-else-if="hasErrors"
              class="w-4 h-4 text-danger"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="currentColor"
            >
              <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
            </svg>
            <!-- Check icon when all done -->
            <svg
              v-else
              class="w-4 h-4 text-success"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="currentColor"
            >
              <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
            </svg>

            <span class="text-sm font-medium text-label">
              {{ headerLabel }}
            </span>
          </button>

          <!-- Action buttons -->
          <div class="flex items-center gap-1">
            <!-- Stop All button -->
            <button
              v-if="hasActiveUploads"
              type="button"
              class="text-xs font-medium text-danger px-2 py-1 rounded hover:bg-danger-surface hover:text-danger-on-surface transition-colors"
              @click="onCancelAll"
              title="Stop all uploads"
            >
              Stop All
            </button>

            <!-- Collapse chevron -->
            <button
              type="button"
              class="text-ink-3 hover:text-ink-2 p-1 rounded hover:bg-hover transition-colors"
              @click="collapsed = !collapsed"
            >
              <svg
                class="w-4 h-4 transition-transform duration-200"
                :class="{ 'rotate-180': !collapsed }"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Upload list -->
        <TransitionGroup
          v-show="!collapsed"
          tag="ul"
          enter-active-class="transition ease-out duration-200"
          enter-from-class="opacity-0 -translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
          class="divide-y divide-line max-h-72 overflow-y-auto"
        >
          <li
            v-for="item in uploads"
            :key="item.id"
            class="px-4 py-3"
          >
            <div class="flex items-start gap-3">

              <!-- Status icon -->
              <div class="flex-shrink-0 mt-0.5">
                <!-- Spinner -->
                <svg
                  v-if="item.status === 'uploading' || item.status === 'pending'"
                  class="w-5 h-5 text-info animate-spin"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <!-- Paused -->
                <svg
                  v-else-if="item.status === 'paused'"
                  class="w-5 h-5 text-warning"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 01.75-.75H9a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75H7.5a.75.75 0 01-.75-.75V5.25zm7 0a.75.75 0 01.75-.75h1.5a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75h-1.5a.75.75 0 01-.75-.75V5.25z" clip-rule="evenodd" />
                </svg>
                <!-- Restored (interrupted upload) -->
                <svg
                  v-else-if="item.isRestored"
                  class="w-5 h-5 text-ink-3"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd" />
                </svg>
                <!-- Error -->
                <svg
                  v-else-if="item.status === 'error'"
                  class="w-5 h-5 text-danger"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd" />
                </svg>
                <!-- Completed -->
                <svg
                  v-else
                  class="w-5 h-5 text-success"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                </svg>
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-ink truncate" :title="item.file?.name || item.fileName">
                  {{ item.file?.name || item.fileName }}
                </p>

                <!-- Progress bar (uploading only) -->
                <div
                  v-if="item.status === 'uploading' || item.status === 'pending'"
                  class="mt-1.5"
                >
                  <div class="w-full bg-surface-sunken rounded-full h-1.5 overflow-hidden">
                    <div
                      class="h-1.5 rounded-full bg-info-fill transition-all duration-300 ease-out"
                      :style="{ width: (item.progress ?? 0) + '%' }"
                    ></div>
                  </div>
                  <p class="text-xs text-ink-3 mt-0.5">
                    {{ Math.round(item.progress ?? 0) }}%
                  </p>
                </div>

                <!-- Error message -->
                <p v-else-if="item.status === 'error'" class="text-xs text-danger mt-0.5">
                  {{ item.error || 'Upload failed' }}
                </p>

                <!-- Restored (interrupted) -->
                <p v-else-if="item.isRestored" class="text-xs text-ink-3 mt-0.5">
                  {{ item.error || 'Upload was interrupted' }}
                </p>

                <!-- Paused -->
                <p v-else-if="item.status === 'paused'" class="text-xs text-warning mt-0.5">
                  Paused at {{ Math.round(item.progress ?? 0) }}%
                </p>

                <!-- Completed -->
                <p v-else class="text-xs text-success mt-0.5">Uploaded</p>
              </div>

              <!-- Action buttons -->
              <div class="flex-shrink-0 flex gap-1 ml-1">
                <!-- Retry button (error state, not restored) -->
                <button
                  v-if="item.status === 'error' && !item.isRestored"
                  type="button"
                  class="text-xs font-medium text-info px-2 py-1 rounded hover:bg-info-surface hover:text-info-on-surface transition-colors"
                  @click="onRetry(item)"
                >
                  Retry
                </button>

                <!-- Cancel button (uploading / pending / paused, not restored) -->
                <button
                  v-if="(item.status === 'uploading' || item.status === 'pending' || item.status === 'paused') && !item.isRestored"
                  type="button"
                  class="text-ink-3 hover:text-ink-2 p-1 rounded hover:bg-hover transition-colors"
                  title="Cancel"
                  @click="onCancel(item)"
                >
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>

                <!-- Clear button (for restored/error items) -->
                <button
                  v-if="item.isRestored || item.status === 'error' || item.status === 'completed'"
                  type="button"
                  class="text-ink-3 hover:text-ink-2 p-1 rounded hover:bg-hover transition-colors"
                  title="Remove from queue"
                  @click="onCancel(item)"
                >
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

            </div>
          </li>
        </TransitionGroup>

      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { PropType } from 'vue'
import type { UploadItem } from '../../Composables/useTusUploadQueue'

/**
 * The floating progress panel for an upload queue. Presentational only: it
 * renders whatever items it is given and calls back — `useTusUploadQueue`
 * supplies all four props, but any queue of the same shape does.
 */
const props = defineProps({
  uploads: {
    type: Array as PropType<UploadItem[]>,
    required: true,
  },
  onRetry: {
    type: Function as PropType<(item: UploadItem) => void>,
    required: true,
  },
  onCancel: {
    type: Function as PropType<(item: UploadItem) => void>,
    required: true,
  },
  onCancelAll: {
    type: Function as PropType<() => void>,
    required: true,
  },
})

const collapsed = ref(false)

const hasActiveUploads = computed(() =>
  props.uploads.some((u) => (u.status === 'uploading' || u.status === 'pending') && !u.isRestored)
)

const hasErrors = computed(() =>
  props.uploads.some((u) => u.status === 'error' && !u.isRestored)
)

const headerLabel = computed(() => {
  const active = props.uploads.filter((u) => u.status === 'uploading' || u.status === 'pending').length
  const errors = props.uploads.filter((u) => u.status === 'error').length
  const restored = props.uploads.filter((u) => u.isRestored).length
  const done = props.uploads.filter((u) => u.status === 'completed').length

  if (active > 0) return `Uploading ${active} file${active > 1 ? 's' : ''}…`
  if (errors > 0) return `${errors} upload${errors > 1 ? 's' : ''} failed`
  if (restored > 0) return `${restored} upload${restored > 1 ? 's' : ''} interrupted`
  return `${done} upload${done !== 1 ? 's' : ''} complete`
})
</script>
