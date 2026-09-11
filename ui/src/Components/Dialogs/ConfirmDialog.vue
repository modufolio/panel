<template>
  <Dialog :is-open="isOpen" :title="title" width="sm" @close="emit('close')">
    <p class="text-sm text-ink-2">{{ message }}</p>

    <template #footer>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="rounded-lg px-4 py-2 text-sm font-medium text-ink-2 hover:bg-hover transition-colors"
          :disabled="loading"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="button"
          :class="[
            'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-50 transition-colors',
            tone === 'primary'
              ? 'bg-primary-fill text-primary-on-fill hover:bg-primary-hover'
              : 'bg-danger-fill text-danger-on-fill hover:bg-danger-hover',
          ]"
          :disabled="loading"
          @click="emit('confirm')"
        >
          <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ confirmLabel }}
        </button>
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import Dialog from './Dialog.vue'

withDefaults(defineProps<{
  isOpen: boolean
  title: string
  message: string
  confirmLabel?: string
  loading?: boolean
  /** Red by default; `primary` for a question that is not destructive. */
  tone?: 'danger' | 'primary'
}>(), { tone: 'danger' })

const emit = defineEmits<{
  confirm: []
  close: []
}>()
</script>
