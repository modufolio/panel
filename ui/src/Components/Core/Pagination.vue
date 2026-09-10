<template>
  <div v-if="links.length > 3" class="flex flex-wrap -mb-1">
    <template v-for="link in links" :key="link.label">
      <div
        v-if="link.url === null"
        class="mb-1 mr-1 px-4 py-3 text-sm leading-4 text-ink-3 border border-line rounded"
        :class="{ 'bg-surface': link.active }"
        v-html="link.label"
      />
      <Link
        v-else
        class="mb-1 mr-1 px-4 py-3 text-sm leading-4 text-ink-2 border border-line rounded hover:bg-surface focus:border-primary focus:text-primary"
        :href="link.url"
        prefetch
        cache-for="10s"
        v-html="link.label"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{
  links: Array<{ url: string | null; label: string; active: boolean }>
}>()
</script>
