<template>
  <Teleport v-if="form.state.visible" to="body">
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="form.state.visible" class="fixed inset-0 z-[60] bg-overlay" @click="form.closeForm()" />
    </Transition>

    <Transition
      enter-active-class="transition ease-out duration-300 transform"
      enter-from-class="translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition ease-in duration-200 transform"
      leave-from-class="translate-x-0"
      leave-to-class="translate-x-full"
    >
      <div
        v-if="form.state.visible"
        ref="panelRef"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        class="fixed inset-y-0 right-0 z-[61] flex w-full max-w-md flex-col bg-surface shadow-lg"
      >
        <div class="flex items-center justify-between border-b border-line px-6 py-4">
          <h2 class="text-lg font-semibold text-ink">{{ title }}</h2>
          <button
            type="button"
            class="text-ink-3 hover:text-ink-2"
            aria-label="Close"
            @click="form.closeForm()"
          >
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <!-- Anything the flat field list cannot express — a contact search,
               a picker — goes above the generated fields. -->
          <slot name="before-fields" />

          <NestedDrawerForm
            :fields="fields"
            :state="form.state"
            :errors="form.errors.value"
            :server-error="form.serverError.value"
            :saving="form.state.saving"
            @update:field="(field, value) => form.setFieldValue(field, value)"
            @submit="() => form.submit(onSave)"
          />
        </div>

        <div class="border-t border-line bg-surface-sunken px-6 py-4">
          <div class="flex items-center justify-between gap-3">
            <button
              v-if="form.state.mode === 'edit' && form.state.recordId"
              type="button"
              class="text-sm text-danger hover:text-danger-on-surface"
              @click="$emit('delete', { data: { id: form.state.recordId } })"
            >
              Delete {{ noun }}
            </button>
            <!-- Placeholder keeps Save on the right when there is nothing to
                 delete, rather than letting it slide across. -->
            <span v-else />
            <button
              type="button"
              class="rounded-lg bg-primary-fill px-4 py-2 text-sm font-medium text-primary-on-fill hover:bg-primary-fill/90 disabled:opacity-50"
              :disabled="form.state.saving"
              @click="() => form.submit(onSave)"
            >
              {{ form.state.saving ? 'Saving…' : `Save ${noun}` }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import NestedDrawerForm from './NestedDrawerForm.vue'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import type { FieldConfig, UseNestedDrawerFormReturn } from '../../Composables/useNestedDrawerForm'

/**
 * The panel that slides over a drawer to add or edit one related record.
 *
 * One component rather than one per relation: the address and connection
 * panels were the same file three times over, which is how the layer
 * registration below came to be missing from all of them at once.
 */
const props = defineProps<{
  form: UseNestedDrawerFormReturn
  fields: FieldConfig[]
  onSave: (data: Record<string, unknown>, mode: 'create' | 'edit') => Promise<void>
  /** What one record is called: "Event", "Address", "Connection". */
  noun: string
}>()

defineEmits<{
  delete: [item: { data: { id: string | number } }]
}>()

const title = computed(() => `${props.form.state.mode === 'edit' ? 'Edit' : 'Add'} ${props.noun}`)

/**
 * Register in the overlay layer stack. Without it the drawer stack beneath
 * stays the topmost layer, so Escape closes the drawer under this panel rather
 * than the panel itself — and that drawer's focus trap keeps reaching in here.
 */
const panelRef = ref<HTMLElement | null>(null)

useDismissableLayer(() => props.form.state.visible, {
  elements: () => [panelRef.value],
  onDismiss: (reason) => { if (reason === 'escape') props.form.closeForm() },
  // The panel draws its own scrim, which already handles a press outside.
  dismissOnOutsidePointer: false,
  modalElement: () => panelRef.value,
})
</script>
