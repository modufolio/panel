<template>
  <Section :heading="label" :description="help" :card="card" :collapsible="collapsible">
    <template v-if="$slots.headerActions" #headerActions>
      <slot name="headerActions" />
    </template>

    <!-- Field grid -->
    <FieldGrid>
      <slot />
    </FieldGrid>

    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </Section>
</template>

<script setup lang="ts">
import Section from './Section.vue'
import FieldGrid from '../Fields/FieldGrid.vue'

defineProps({
  label: {
    type: String,
    default: '',
  },
  help: {
    type: String,
    default: '',
  },
  card: {
    type: Boolean,
    default: true,
  },
  collapsible: {
    type: Boolean,
    default: false,
  },
})
</script>
