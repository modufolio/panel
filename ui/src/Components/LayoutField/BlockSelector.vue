<template>
  <Modal :show="show" max-width="md" @close="emit('close')">
    <template #header>
      <h3 class="text-base font-semibold text-ink">{{ title }}</h3>
    </template>

    <div class="ui-block-selector grid w-96 max-w-full grid-cols-2 gap-2" role="listbox" :aria-label="title">
      <button
        v-for="type in types"
        :key="type.type"
        type="button"
        role="option"
        :aria-selected="false"
        :disabled="disabledTypes.includes(type.type)"
        class="flex items-center gap-3 rounded-lg border border-line-strong px-3 py-2.5 text-left text-sm text-ink transition-colors hover:bg-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-focus disabled:cursor-not-allowed disabled:opacity-40"
        @click="emit('select', type.type)"
      >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-surface-sunken text-ink-2">
          <Icon :name="type.icon" class="h-4 w-4" />
        </span>
        {{ type.label }}
      </button>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import Modal from '../Core/Modal.vue'
import Icon from '../Core/Icon.vue'
import type { BlockType } from './layoutModel'

defineProps({
  show: { type: Boolean, default: false },
  types: { type: Array as PropType<readonly BlockType[]>, required: true },
  /** Types shown but not choosable — the block's own type when converting. */
  disabledTypes: { type: Array as PropType<string[]>, default: () => [] },
  title: { type: String, default: 'Add a block' },
})

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'select', type: string): void
}>()
</script>
