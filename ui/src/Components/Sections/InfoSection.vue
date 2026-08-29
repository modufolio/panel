<template>
  <Section :heading="label" :description="help" :card="card">
    <template v-if="$slots.headerActions" #headerActions>
      <slot name="headerActions" />
    </template>

    <!-- Items from prop -->
    <dl v-if="items && items.length > 0" class="ui-info-list divide-y divide-gray-100">
      <div
        v-for="item in items"
        :key="item.label"
        class="ui-info-item py-3 flex gap-4 text-sm"
        :class="stacked ? 'flex-col gap-1' : 'sm:flex-row'"
      >
        <dt class="font-medium text-gray-500 shrink-0" :class="stacked ? '' : 'sm:w-40'">
          {{ item.label }}
        </dt>
        <dd class="text-gray-900">
          <slot :name="`item-${item.key}`">
            <span v-if="item.value !== null && item.value !== undefined && item.value !== ''">
              {{ item.value }}
            </span>
            <span v-else class="text-gray-400">—</span>
          </slot>
        </dd>
      </div>
    </dl>

    <!-- Slot for custom content -->
    <slot v-else />
  </Section>
</template>

<script setup lang="ts">
import Section from './Section.vue'

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
  stacked: {
    type: Boolean,
    default: false,
  },
  items: {
    type: Array as () => Array<{ label: string; key: string; value?: unknown }>,
    default: () => [],
  },
})
</script>
