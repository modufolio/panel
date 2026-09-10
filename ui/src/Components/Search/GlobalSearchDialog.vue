<template>
  <Modal :show="show" max-width="xl" @close="close">
    <template #header>
      <div class="flex items-center gap-2">
        <Icon name="search" class="h-5 w-5 text-ink-3" />
        <input
          ref="input"
          :value="search.query.value"
          type="search"
          class="ui-global-search-input w-full border-0 bg-transparent text-base text-ink placeholder:text-ink-3 focus:outline-none focus:ring-0"
          :placeholder="placeholder"
          autocomplete="off"
          role="combobox"
          aria-autocomplete="list"
          aria-controls="ui-global-search-results"
          :aria-expanded="search.hits.value.length > 0"
          @input="search.update(($event.target as HTMLInputElement).value)"
          @keydown.down.prevent="search.move(1)"
          @keydown.up.prevent="search.move(-1)"
          @keydown.enter.prevent="search.open()"
        >
      </div>
    </template>

    <div id="ui-global-search-results" role="listbox" class="-mx-4 -mb-4 max-h-96 overflow-y-auto">
      <p v-if="search.loading.value" class="px-4 py-6 text-center text-sm text-ink-3">Searching…</p>

      <p v-else-if="search.query.value.trim() !== '' && search.hits.value.length === 0" class="px-4 py-6 text-center text-sm text-ink-3">
        Nothing matches “{{ search.query.value.trim() }}”.
      </p>

      <p v-else-if="search.hits.value.length === 0" class="px-4 py-6 text-center text-sm text-ink-3">
        Type to search across the panel.
      </p>

      <template v-for="group in search.groups.value" v-else :key="group.key">
        <div class="ui-global-search-group px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-ink-3">
          {{ group.label }}
          <span v-if="group.total > group.items.length" class="ml-1 font-normal normal-case text-ink-3">{{ group.items.length }} of {{ group.total }}</span>
        </div>
        <button
          v-for="hit in group.items"
          :key="hit.href"
          type="button"
          role="option"
          class="ui-global-search-hit flex w-full items-baseline justify-between gap-3 px-4 py-2 text-left text-sm"
          :class="search.indexOf(hit) === search.activeIndex.value ? 'bg-primary-surface text-primary-on-surface' : 'text-ink hover:bg-hover'"
          :aria-selected="search.indexOf(hit) === search.activeIndex.value"
          @mouseenter="search.activeIndex.value = search.indexOf(hit)"
          @click="search.open(hit)"
        >
          <span class="truncate">{{ hit.title }}</span>
          <span v-if="hit.details.length" class="shrink-0 text-xs text-ink-3">{{ hit.details.join(' · ') }}</span>
        </button>
      </template>
    </div>
  </Modal>
</template>

<script setup lang="ts">
/**
 * The search across resources, opened from the top bar or ⌘K: one input,
 * hits grouped by resource, arrow keys and Enter to open one. What is
 * searched, and for whom, the server decided.
 */
import { nextTick, ref, watch } from 'vue'
import Modal from '../Core/Modal.vue'
import Icon from '../Core/Icon.vue'
import { useGlobalSearch } from './useGlobalSearch'

const props = defineProps({
  show: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Search…' },
})

const emit = defineEmits<{ (e: 'close'): void }>()

const search = useGlobalSearch()
const input = ref<HTMLInputElement | null>(null)

watch(() => props.show, async (open) => {
  if (open) {
    await nextTick()
    input.value?.focus()
  } else {
    search.reset()
  }
})

function close(): void {
  emit('close')
}
</script>
