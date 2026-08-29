<template>
  <div v-if="links.length > 3" class="flex flex-wrap -mb-1">
    <template v-for="link in links" :key="link.label">
      <div
        v-if="link.url === null"
        class="mb-1 mr-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded"
        :class="{ 'bg-white': link.active }"
        v-html="link.label"
      />
      <Link
        v-else
        class="mb-1 mr-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500"
        :href="link.url"
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
