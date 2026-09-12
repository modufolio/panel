<template>
  <form class="space-y-3" @submit.prevent="submit">
    <div>
      <label class="block text-xs font-medium text-ink-2 mb-1">Label</label>
      <input
        v-model="form.label"
        type="text"
        required
        class="ui-input ui-input-auto w-full py-1.5"
        :placeholder="form.type === 'label' ? 'Section header…' : 'Link text…'"
      />
    </div>

    <div v-if="form.type === 'link'">
      <label class="block text-xs font-medium text-ink-2 mb-1">URL</label>
      <input
        v-model="form.url"
        type="url"
        required
        placeholder="https://…"
        class="ui-input ui-input-auto w-full py-1.5"
      />
    </div>

    <div v-if="form.type === 'link'">
      <label class="block text-xs font-medium text-ink-2 mb-1">Open in</label>
      <select
        v-model="form.target"
        class="ui-input ui-input-auto w-full py-1.5"
      >
        <option value="_self">Same tab</option>
        <option value="_blank">New tab</option>
      </select>
    </div>

    <!-- Type toggle -->
    <div class="flex gap-2">
      <button
        v-for="t in types"
        :key="t.value"
        type="button"
        class="flex-1 text-xs py-1 rounded border transition-colors"
        :class="form.type === t.value
          ? 'bg-primary-fill text-primary-on-fill border-primary'
          : 'border-line text-ink-2 hover:border-primary'"
        @click="form.type = t.value"
      >
        {{ t.label }}
      </button>
    </div>

    <button
      type="submit"
      class="w-full py-1.5 text-sm bg-gray-fill text-gray-on-fill rounded-md hover:bg-gray-hover transition-colors"
    >
      Add {{ form.type === 'label' ? 'Label' : 'Link' }}
    </button>
  </form>
</template>

<script setup lang="ts">
import { reactive } from 'vue'

const emit = defineEmits<{
  'add-item': [payload: { type: string, label: string, url: string | null, target: string, page_slug: null }]
}>()

const types = [
  { value: 'link',  label: 'External Link' },
  { value: 'label', label: 'Label Header'  },
]

const form = reactive({
  type:   'link',
  label:  '',
  url:    '',
  target: '_self',
})

const submit = () => {
  emit('add-item', {
    type:      form.type,
    label:     form.label,
    url:       form.type === 'link' ? form.url : null,
    target:    form.target,
    page_slug: null,
  })
  form.label = ''
  form.url   = ''
}
</script>
