<template>
  <div
    class="group flex items-center gap-2 px-2 py-1.5 rounded-lg bg-surface border border-hairline select-none hover:border-line-strong transition-colors"
    :class="{ 'opacity-50': !item.is_visible }"
    draggable="true"
    @dragstart="$emit('dragstart', $event)"
    @dragend="$emit('dragend', $event)"
  >
    <!-- Drag handle -->
    <span
      class="flex items-center justify-center w-6 h-6 rounded text-ink-3 group-hover:text-ink-2 cursor-grab active:cursor-grabbing shrink-0"
      title="Drag to reorder"
    >
      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
        <path d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm0 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm0 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm8-12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm0 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm0 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
      </svg>
    </span>

    <!-- Type icon -->
    <span class="shrink-0 text-ink-3">
      <svg v-if="item.type === 'page'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <svg v-else-if="item.type === 'album'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      <svg v-else-if="item.type === 'link'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
      </svg>
      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
      </svg>
    </span>

    <!-- Label (inline edit) — a fixed budget: it's a short name, not the
         field worth reading in full, so it yields room to the URL below. -->
    <input
      :value="item.label"
      class="w-28 sm:w-40 shrink-0 text-sm bg-transparent border-none outline-none focus:bg-hover focus:px-1 rounded truncate"
      :class="item.type === 'label' ? 'text-ink-3 italic' : 'text-ink'"
      @change="$emit('update-label', item.id, ($event.target as HTMLInputElement).value)"
    />

    <!-- URL hint — gets whatever room is left, which is most of the row:
         this is what you're actually scanning the tree to verify. -->
    <span v-if="item.url" class="hidden sm:block flex-1 min-w-0 text-xs text-ink-3 truncate" :title="item.url">
      {{ item.url }}
    </span>
    <span v-else class="flex-1 min-w-0" />

    <!-- Actions -->
    <div class="flex items-center gap-0.5 shrink-0">
      <!-- Keyboard-operable reorder: the only way to move an item without a
           pointer, and a faster way to do it with one. -->
      <button
        type="button"
        class="p-1 rounded text-ink-3 hover:bg-hover hover:text-ink-2 transition-colors disabled:opacity-30 disabled:pointer-events-none"
        title="Move up"
        :disabled="!canMoveUp"
        @click="$emit('move-up', item.id)"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
        </svg>
      </button>
      <button
        type="button"
        class="p-1 rounded text-ink-3 hover:bg-hover hover:text-ink-2 transition-colors disabled:opacity-30 disabled:pointer-events-none"
        title="Move down"
        :disabled="!canMoveDown"
        @click="$emit('move-down', item.id)"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>

      <!-- Visibility toggle -->
      <button
        type="button"
        class="p-1 rounded hover:bg-hover transition-colors"
        :class="item.is_visible ? 'text-ink-2' : 'text-ink-3'"
        :title="item.is_visible ? 'Hide' : 'Show'"
        @click="$emit('toggle-visibility', item.id)"
      >
        <svg v-if="item.is_visible" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        </svg>
      </button>

      <!-- Delete -->
      <button
        type="button"
        class="p-1 rounded hover:bg-danger-surface text-ink-3 hover:text-danger transition-colors"
        title="Remove"
        @click="$emit('remove', item.id)"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { NavigationTreeNode } from '../../Composables/useNavigation'

defineProps<{
  item: NavigationTreeNode
  canMoveUp?: boolean
  canMoveDown?: boolean
}>()

defineEmits<{
  dragstart: [event: DragEvent]
  dragend: [event: DragEvent]
  'toggle-visibility': [id: number]
  remove: [id: number]
  'update-label': [id: number, label: string]
  'move-up': [id: number]
  'move-down': [id: number]
}>()
</script>
