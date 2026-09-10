<template>
  <slot v-if="!error" />
  <slot v-else name="fallback" :error="error" :reset="reset">
    <div class="rounded-lg border border-danger/30 bg-danger-surface p-4 text-sm text-danger-on-surface">
      <p class="font-medium">{{ label }}</p>
      <!-- The raw message is supporting detail under the label, so it steps
           back with opacity rather than a second, paler danger token. -->
      <p v-if="error?.message" class="mt-1 break-words opacity-80">{{ error.message }}</p>
      <button type="button" class="mt-2 font-medium text-danger-on-surface underline" @click="reset">
        Try again
      </button>
    </div>
  </slot>
</template>

<script setup lang="ts">
/**
 * Isolates render/lifecycle errors thrown by its subtree (the analog of Solid's
 * <ErrorBoundary>) so a throw inside one risky widget — the rich-text editors,
 * the upload queue, the autofocus overlay — doesn't blank the whole page.
 *
 *   <ErrorBoundary label="The editor failed to load.">
 *     <BlockEditorField v-model="content" />
 *   </ErrorBoundary>
 *
 * Note: onErrorCaptured only sees errors from rendering, setup, watchers and
 * lifecycle hooks of descendants — not async/event-handler errors, which the
 * global handler in Plugins/errorHandler.js covers.
 */
import { ref, onErrorCaptured } from 'vue'

withDefaults(defineProps<{ label?: string }>(), {
  label: 'Something went wrong in this section.',
})

const error = ref<Error | null>(null)

onErrorCaptured((err) => {
  error.value = err instanceof Error ? err : new Error(String(err))
  return false // stop propagation past this boundary
})

function reset() {
  error.value = null
}
</script>
