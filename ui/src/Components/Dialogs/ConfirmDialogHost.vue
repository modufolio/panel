<template>
  <ConfirmDialog
    :is-open="state.open"
    :title="state.title"
    :message="state.message"
    :confirm-label="state.confirmLabel"
    :tone="state.tone"
    @confirm="confirm(true)"
    @close="confirm(false)"
  />
</template>

<script setup lang="ts">
/**
 * The single mounted dialog behind {@link showConfirm}, rendered once by
 * AppLayout the way `<ErrorModal />` is. Pages call the function; nobody
 * mounts this themselves.
 */
import ConfirmDialog from './ConfirmDialog.vue'
import { useConfirmDialog } from './confirm'

const { state, confirm } = useConfirmDialog()
</script>
