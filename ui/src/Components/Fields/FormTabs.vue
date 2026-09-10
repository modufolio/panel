<template>
  <div class="ui-form-tabs col-span-12 -mb-1 border-b border-line">
    <nav
      ref="tablistRef"
      class="-mb-px flex items-center gap-6 overflow-x-auto"
      role="tablist"
      aria-label="Form sections"
      @keydown="onKeydown"
    >
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        role="tab"
        :aria-selected="tab.key === modelValue"
        :tabindex="tab.key === modelValue ? 0 : -1"
        :data-tab="tab.key"
        class="flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-1 py-2.5 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-app"
        :class="tab.key === modelValue
          ? 'border-primary text-primary'
          : 'border-transparent text-ink-3 hover:border-line-strong hover:text-ink'"
        @click="emit('update:modelValue', tab.key)"
      >
        <Icon v-if="tab.icon" :name="tab.icon" class="h-4 w-4" aria-hidden="true" />
        <span>{{ tab.label }}</span>
        <span
          v-if="errors[tab.key]"
          class="ui-form-tab-errors ml-0.5 inline-flex items-center rounded-full bg-danger-surface px-2 py-0.5 text-xs font-medium text-danger-on-surface"
          :title="`${errors[tab.key]} field(s) need attention`"
        >{{ errors[tab.key] }}</span>
      </button>
    </nav>
  </div>
</template>

<script setup lang="ts">
/**
 * The tab bar of a form with tabs: one button per declared tab, the count of
 * fields with an error on each, and arrow-key movement between them.
 *
 * Only the bar. BlueprintForm decides which fields belong to the active tab
 * and renders them beneath; this component never sees a field.
 */
import { ref } from 'vue'
import Icon from '../Core/Icon.vue'

const props = defineProps<{
  tabs: Array<{ key: string; label: string; icon?: string | null }>
  modelValue: string
  /** Fields with a shown error, counted per tab key. */
  errors: Record<string, number>
}>()

const emit = defineEmits<{ 'update:modelValue': [key: string] }>()

const tablistRef = ref<HTMLElement | null>(null)

function onKeydown(event: KeyboardEvent): void {
  const keys = props.tabs.map((tab) => tab.key)
  const index = keys.indexOf(props.modelValue)
  if (index === -1) return

  const next = event.key === 'ArrowRight' ? (index + 1) % keys.length
    : event.key === 'ArrowLeft' ? (index - 1 + keys.length) % keys.length
    : event.key === 'Home' ? 0
    : event.key === 'End' ? keys.length - 1
    : null

  if (next === null) return

  event.preventDefault()
  emit('update:modelValue', keys[next])
  tablistRef.value?.querySelector<HTMLElement>(`[data-tab="${CSS.escape(keys[next])}"]`)?.focus()
}
</script>
