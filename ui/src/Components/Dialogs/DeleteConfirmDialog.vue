<template>
  <Dialog :is-open="state.open" :title="heading" width="md" @close="$emit('close')">
    <!-- Waiting on the server's answer: promise nothing yet. -->
    <p v-if="state.loading" class="text-sm text-gray-600">Checking what depends on this record…</p>

    <!-- Something is in the way. Naming it is the whole point: "cannot delete"
         without saying why leaves the user with nowhere to go. -->
    <template v-else-if="plan?.blocked">
      <p class="text-sm text-gray-700">
        It is referenced by the following, which must be changed or removed first:
      </p>
      <ul class="mt-3 space-y-1 text-sm text-gray-900">
        <li v-for="entry in plan.protected ?? []" :key="entry" class="rounded bg-red-50 px-3 py-2">
          {{ entry }}
        </li>
      </ul>
    </template>

    <!-- Reversible, so there is no blast radius to show. -->
    <p v-else-if="plan?.soft" class="text-sm text-gray-700">
      This moves the record to the trash, where it can be restored.
    </p>

    <!-- The consequences, stated. -->
    <template v-else-if="plan">
      <p class="text-sm text-gray-700">This cannot be undone. The following will be deleted:</p>

      <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Summary</h3>
      <ul class="mt-2 space-y-1 text-sm text-gray-900">
        <li v-for="[name, count] in summary" :key="name">{{ name }}: {{ count }}</li>
      </ul>

      <template v-if="rows.length > 0">
        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Objects</h3>
        <ul class="mt-2 max-h-56 space-y-1 overflow-y-auto text-sm text-gray-900">
          <li
            v-for="(row, index) in rows"
            :key="`${row.label}-${index}`"
            :style="{ paddingLeft: `${row.depth * 16}px` }"
          >
            {{ row.label }}
          </li>
        </ul>
      </template>
    </template>

    <!-- No preview endpoint: the plain confirmation, at least consistent. -->
    <p v-else class="text-sm text-gray-700">{{ message }}</p>

    <p v-if="state.error" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ state.error }}
    </p>

    <template #footer>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100"
          @click="$emit('close')"
        >
          {{ plan?.blocked ? 'Close' : 'Cancel' }}
        </button>
        <button
          v-if="!plan?.blocked && !state.loading"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
          :disabled="state.deleting"
          @click="$emit('confirm')"
        >
          <svg v-if="state.deleting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
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
import { computed } from 'vue'
import Dialog from './Dialog.vue'
import type { DeleteConfirmationState, DeletionNode, DeletionPlan } from '../../Composables/useDeleteConfirmation'

/**
 * The confirmation half of {@see useDeleteConfirmation}.
 *
 * Renders whichever of four states the composable is in: still asking, blocked,
 * reversible, or here-is-what-goes. A page with no preview endpoint gets the
 * last-resort message instead — the same dialog, so deleting looks the same
 * everywhere even where the server cannot yet say what it costs.
 */
const props = withDefaults(defineProps<{
  state: DeleteConfirmationState
  /** Noun for the heading: "Delete this movie?" */
  label?: string
  /** Shown when there is no plan to describe. */
  message?: string
  confirmLabel?: string
}>(), {
  label: 'record',
  message: 'This cannot be undone.',
  confirmLabel: 'Yes, delete',
})

defineEmits<{ confirm: []; close: [] }>()

const plan = computed<DeletionPlan | null>(() => props.state.plan)

const heading = computed(() =>
  plan.value?.blocked
    ? `Cannot delete this ${props.label}`
    : `Delete this ${props.label}?`,
)

const summary = computed<Array<[string, number]>>(() => [
  ...Object.entries(plan.value?.counts ?? {}),
  ...Object.entries(plan.value?.linkCounts ?? {}),
] as Array<[string, number]>)

/** Flatten the nested plan depth-first, keeping the indent level. */
function flatten(nodes: DeletionNode[], depth = 0): Array<{ label: string; depth: number }> {
  return (nodes ?? []).flatMap((node) => [
    { label: node.label, depth },
    ...flatten(node.children ?? [], depth + 1),
  ])
}

const rows = computed(() => flatten(plan.value?.nested ?? []))
</script>
