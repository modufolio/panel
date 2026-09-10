<template>
  <Dialog
    :is-open="state.open"
    :title="state.title"
    width="md"
    @update:is-open="close"
  >
    <div class="ui-error-modal flex gap-4">
      <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-danger-surface">
        <Icon name="exclamation-triangle" class="h-6 w-6 text-danger" />
      </div>
      <div class="pt-1">
        <p class="text-sm text-ink-2">{{ state.message }}</p>
        <!-- The status is for the person reporting the bug, not the person
             reading the sentence: present, quiet, not part of the message. -->
        <p v-if="state.status" class="mt-2 text-xs text-ink-3">Error {{ state.status }}</p>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end">
        <Action label="Close" color="gray" variant="outlined" @click="close" />
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import Dialog from './Dialog.vue'
import Action from '../Actions/Action.vue'
import Icon from '../Core/Icon.vue'
import { useErrorModal } from '../Notifications/errorModal'

/**
 * The blocking half of the panel's error reporting: a failure a toast would
 * let slip past — a dead session, a refused action, a broken server — shown
 * until the viewer dismisses it. Which statuses land here is configuration,
 * not markup: see `createPanel({ errorMessages })`.
 *
 * AppLayout mounts one; an app that lays out its own chrome mounts this
 * itself, once.
 */
const { state, close } = useErrorModal()
</script>
