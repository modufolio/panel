<template>
  <div class="ui-layout-block-image">
    <button
      type="button"
      class="group relative block w-full overflow-hidden rounded-md bg-surface-sunken focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
      :class="src ? '' : 'border border-dashed border-line-strong'"
      :disabled="disabled"
      :title="src ? 'Change image' : 'Choose image'"
      @click="picker = true"
    >
      <template v-if="src">
        <img
          :src="src"
          :alt="alt"
          class="block w-full"
          :style="{ aspectRatio: ratio !== '' ? ratio.replace('/', ' / ') : 'auto', objectFit: crop ? 'cover' : 'contain', maxHeight: '24rem' }"
        />
        <span class="absolute inset-0 flex items-center justify-center bg-overlay opacity-0 transition-opacity group-hover:opacity-100">
          <span class="rounded-md bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow">Change image</span>
        </span>
      </template>
      <span v-else class="flex h-28 flex-col items-center justify-center gap-1 text-ink-3">
        <Icon name="photo" class="h-6 w-6" />
        <span class="text-xs">Choose an image…</span>
      </span>
    </button>

    <div class="mt-2 grid grid-cols-12 gap-2">
      <input
        :value="alt"
        type="text"
        placeholder="Alt text"
        :disabled="disabled"
        aria-label="Alt text"
        class="ui-input col-span-12 py-1 text-xs sm:col-span-6"
        @input="emit('update', { alt: ($event.target as HTMLInputElement).value })"
      />
      <select
        :value="ratio"
        :disabled="disabled"
        aria-label="Aspect ratio"
        class="ui-input col-span-7 py-1 text-xs sm:col-span-3"
        @change="emit('update', { ratio: ($event.target as HTMLSelectElement).value })"
      >
        <option v-for="option in IMAGE_RATIOS" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <label class="col-span-5 flex items-center gap-1.5 text-xs text-ink-2 sm:col-span-3">
        <input
          type="checkbox"
          :checked="crop"
          :disabled="disabled || ratio === ''"
          class="rounded border-line-strong"
          @change="emit('update', { crop: ($event.target as HTMLInputElement).checked })"
        />
        Crop
      </label>
    </div>

    <MediaPickerDialog :is-open="picker" @close="picker = false" @select="choose" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, type PropType } from 'vue'
import Icon from '../../Core/Icon.vue'
import MediaPickerDialog, { type MediaItem } from '../../Media/MediaPickerDialog.vue'
import { IMAGE_RATIOS } from '../layoutModel'

const props = defineProps({
  content: { type: Object as PropType<Record<string, unknown>>, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits<{
  (e: 'update', patch: Record<string, unknown>): void
}>()

const picker = ref(false)

const str = (key: string): string => (typeof props.content[key] === 'string' ? (props.content[key] as string) : '')

const src = computed(() => str('thumbnail_url') || str('url'))
const alt = computed(() => str('alt'))
const ratio = computed(() => str('ratio'))
const crop = computed(() => props.content.crop === true)

function choose(item: MediaItem): void {
  picker.value = false
  const filename = (item as MediaItem & { filename?: string }).filename ?? item.original_filename
  emit('update', {
    id: String(item.id),
    media: filename,
    url: item.url,
    thumbnail_url: item.thumbnail_url,
    alt: alt.value !== '' ? alt.value : (item.alt_text ?? ''),
  })
}
</script>
