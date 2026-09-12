<template>
  <Modal :show="show" max-width="2xl" @close="emit('close')">
    <template #header>
      <h3 class="text-base font-semibold text-ink">{{ title }}</h3>
    </template>

    <!-- Each preset is drawn as the grid it produces, rather than printed as
         "1/3 1/3 1/3": a picture of a row is read faster than a fraction
         list. -->
    <div class="ui-layout-selector grid w-[36rem] max-w-full grid-cols-3 gap-4" role="listbox" :aria-label="title">
      <button
        v-for="(columns, i) in layouts"
        :key="i"
        type="button"
        role="option"
        :aria-selected="value !== null && sameLayout(columns, value)"
        :aria-label="columns.join(' + ')"
        :title="columns.join(' + ')"
        class="ui-layout-selector-option group rounded-lg border-2 p-1 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
        :class="value !== null && sameLayout(columns, value)
          ? 'border-primary bg-primary-surface'
          : 'border-line-strong hover:border-ink-3 hover:bg-hover'"
        @click="emit('select', [...columns])"
      >
        <div class="grid h-16 grid-cols-12 gap-0.5 overflow-hidden rounded bg-ink-3/60">
          <div
            v-for="(width, c) in columns"
            :key="c"
            class="bg-surface group-hover:bg-surface-sunken"
            :style="{ gridColumn: `span ${widthToSpan(width)}` }"
          />
        </div>
      </button>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import Modal from '../Core/Modal.vue'
import { sameLayout, widthToSpan } from './layoutModel'

defineProps({
  show: { type: Boolean, default: false },
  /** Presets as width lists: `[['1/1'], ['1/2', '1/2']]`. */
  layouts: { type: Array as PropType<string[][]>, required: true },
  /** The preset to mark as current, when changing an existing row. */
  value: { type: Array as PropType<string[] | null>, default: null },
  title: { type: String, default: 'Select a layout' },
})

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'select', columns: string[]): void
}>()
</script>
