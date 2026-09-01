<script setup lang="ts">
/**
 * Help text under a control.
 *
 * Carries the id the control points at with `aria-describedby` — the reason
 * this is a component and not a `<p>` in each field is that the id, the class
 * and the spacing were being re-typed per field, and half of them forgot the
 * id, so the help was visible but never announced.
 */
defineProps({
  id: { type: String, default: undefined },
})
</script>

<template>
  <p :id="id" class="ui-field-help mt-1.5 text-sm text-gray-600 dark:text-gray-400">
    <slot />
  </p>
</template>
