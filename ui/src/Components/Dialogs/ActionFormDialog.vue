<template>
  <Modal :show="true" max-width="lg" @close="emit('close')">
    <template #header>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ action.label }}</h3>
    </template>

    <form class="space-y-4" @submit.prevent="submit">
      <p v-if="message" class="text-sm text-gray-600 dark:text-gray-300">{{ message }}</p>

      <BlueprintForm
        v-if="fields.length"
        v-model="data"
        :fields="fields"
        :errors="errors"
      />

      <div class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-white/5"
          :disabled="submitting"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
          :disabled="submitting"
        >
          {{ action.submitLabel ?? action.label }}
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup lang="ts">
/**
 * The dialog a `form` action opens: the fields the server declared for it,
 * rendered by the blueprint form, and posted to the action's URL with the
 * method it named — beside the selection's ids for a bulk action. A
 * confirmation-only action shows its message and the two buttons.
 *
 * Validation errors come back the Inertia way and land on the fields; a
 * success closes the dialog and leaves the page to the redirect or reload
 * the server answered with.
 */
import { computed, ref, type PropType } from 'vue'
import { router } from '@inertiajs/vue3'
import type { Method, RequestPayload } from '@inertiajs/core'
import Modal from '../Core/Modal.vue'
import BlueprintForm from '../Fields/BlueprintForm.vue'
import { fieldsFromSpec, type FieldSpec } from '../Fields/fieldsFromSpec'

interface DialogAction {
  label: string
  fields?: FieldSpec[]
  method?: string
  submitLabel?: string
  confirmMessage?: string
}

const props = defineProps({
  action: { type: Object as PropType<DialogAction>, required: true },
  url: { type: String, required: true },
  /** Sent with the form's values: a bulk action's `ids`. */
  extra: { type: Object as PropType<Record<string, unknown>>, default: () => ({}) },
  /** How many records the action touches, for `{count}` in the message. */
  count: { type: Number, default: 1 },
})

const emit = defineEmits<{ (e: 'close'): void }>()

const fields = computed(() => fieldsFromSpec(props.action.fields ?? []))

const data = ref<Record<string, unknown>>(Object.fromEntries(
  (props.action.fields ?? []).map((field) => [field.key, field.default ?? null]),
))
const errors = ref<Record<string, string>>({})
const submitting = ref(false)

const message = computed(() => (props.action.confirmMessage ?? '').replace('{count}', String(props.count)))

function submit(): void {
  const method = ((props.action.method ?? 'post').toLowerCase()) as Method

  submitting.value = true
  errors.value = {}

  router.visit(props.url, {
    method,
    data: { ...props.extra, ...data.value } as RequestPayload,
    preserveScroll: true,
    preserveState: true,
    onError: (received) => {
      errors.value = received as Record<string, string>
    },
    onSuccess: () => emit('close'),
    onFinish: () => {
      submitting.value = false
    },
  })
}
</script>
