<template>
  <Dialog
    :is-open="isOpen"
    title="Change Password"
    description="Enter your current password and choose a new one."
    width="md"
    @update:is-open="$emit('update:isOpen', $event)"
  >
    <form @submit.prevent="submit">
      <div class="space-y-4">
        <TextField
          v-model="form.current_password"
          :error="errors.current_password"
          label="Current Password"
          type="password"
          autocomplete="current-password"
          required
        />
        <TextField
          v-model="form.password"
          :error="errors.password"
          label="New Password"
          type="password"
          autocomplete="new-password"
          required
        />
        <TextField
          v-model="form.password_confirm"
          :error="errors.password_confirm"
          label="Confirm New Password"
          type="password"
          autocomplete="new-password"
          required
        />
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <Action
          label="Cancel"
          color="secondary"
          @click="cancel"
        />
        <Action
          label="Update Password"
          color="primary"
          :disabled="processing"
          @click="submit"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import Dialog from './Dialog.vue'
import TextField from '../Fields/TextField.vue'
import Action from '../Actions/Action.vue'
import { useToast } from '../Notifications/useToast'
import { panelUrl } from '../../Utils/url'
import { apiFetch, ApiError } from '../../Utils/apiFetch'

/**
 * Change-password form over the panel's own endpoint. The three fields go to
 * `POST {baseUrl}/profile/password` and the server's per-field errors come
 * back onto the fields — it is the server that decides what a valid password
 * is, so nothing here restates its rules.
 *
 * Open it from a user-menu item; AppLayout's default menu has one.
 */

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  /** Where the form posts, relative to the panel's base URL. */
  endpoint: {
    type: String,
    default: '/profile/password',
  },
})

const emit = defineEmits(['update:isOpen'])

const toast = useToast()
const form = reactive({ current_password: '', password: '', password_confirm: '' })
const errors = reactive<Record<string, string>>({})
const processing = ref(false)

function resetForm() {
  form.current_password = ''
  form.password = ''
  form.password_confirm = ''
  Object.keys(errors).forEach(k => delete errors[k])
}

async function submit() {
  Object.keys(errors).forEach(k => delete errors[k])
  processing.value = true

  try {
    await apiFetch(panelUrl(props.endpoint), {
      method: 'POST',
      body: {
        current_password: form.current_password,
        password: form.password,
        password_confirm: form.password_confirm,
      },
    })

    resetForm()
    emit('update:isOpen', false)
    toast.success('Your password has been updated.', 'Password changed')
  } catch (error) {
    if (error instanceof ApiError) {
      const fieldErrors = (error.body as { errors?: Record<string, string | string[]> } | null)?.errors ?? {}
      Object.entries(fieldErrors).forEach(([field, messages]) => {
        errors[field] = Array.isArray(messages) ? messages[0] : String(messages)
      })
    } else {
      toast.error('Something went wrong. Please try again.', 'Error')
    }
  } finally {
    processing.value = false
  }
}

function cancel() {
  resetForm()
  emit('update:isOpen', false)
}

watch(() => props.isOpen, (open) => {
  if (!open) resetForm()
})
</script>
