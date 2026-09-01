<script setup lang="ts">
/**
 * A validation message under a control.
 *
 * `role="alert"` is not optional here: an error that appears after a failed
 * submit is a change the user did not initiate, and without the role a screen
 * reader announces nothing. Several fields had it, several did not.
 */
defineProps({
  id: { type: String, default: undefined },
})
</script>

<template>
  <p :id="id" role="alert" class="ui-field-error mt-1.5 text-sm text-danger-600">
    <slot />
  </p>
</template>
