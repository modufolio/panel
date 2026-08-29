<template>
  <Head title="Set New Password" />
  <div class="flex items-center justify-center p-6 min-h-screen bg-gray-50">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="mb-8 text-center">
        <!-- Consumers supply their own mark. -->
        <slot name="logo" />
      </div>

      <!-- Invalid / expired link -->
      <div v-if="!valid" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 p-8 text-center space-y-4">
        <div class="flex items-center justify-center w-12 h-12 mx-auto rounded-full bg-danger-50">
          <svg class="w-6 h-6 text-danger-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
          </svg>
        </div>
        <div>
          <h2 class="text-xl font-semibold text-gray-950">Link invalid or expired</h2>
          <p class="mt-2 text-sm text-gray-600">
            This password reset link is no longer valid. It may have already been used or has expired (links are valid for 24 hours).
          </p>
        </div>
        <a :href="panelUrl('/forgot-password')" class="inline-block text-sm font-medium text-primary-600 hover:text-primary-700">
          Request a new link
        </a>
      </div>

      <!-- Reset form -->
      <div v-else class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5">
        <form @submit.prevent="submit" class="p-8 space-y-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-950">Set new password</h2>
            <p class="mt-1 text-sm text-gray-600">
              Setting a new password for <span class="font-medium text-gray-800">{{ email }}</span>
            </p>
          </div>

          <TextField
            v-model="form.password"
            :error="passwordError"
            label="New password"
            type="password"
            placeholder="At least 12 characters"
            autocomplete="new-password"
            required
          />

          <TextField
            v-model="form.password_confirm"
            :error="form.errors.password_confirm"
            label="Confirm new password"
            type="password"
            placeholder="Repeat your new password"
            autocomplete="new-password"
            required
          />

          <!-- Password requirements hint -->
          <ul class="text-xs text-gray-500 space-y-1">
            <li :class="form.password.length >= 12 ? 'text-success-600' : ''">
              ✓ At least 12 characters
            </li>
            <li :class="/[A-Z]/.test(form.password) ? 'text-success-600' : ''">
              ✓ One uppercase letter
            </li>
            <li :class="/[a-z]/.test(form.password) ? 'text-success-600' : ''">
              ✓ One lowercase letter
            </li>
            <li :class="/[0-9]/.test(form.password) ? 'text-success-600' : ''">
              ✓ One number
            </li>
            <li :class="/[^A-Za-z0-9]/.test(form.password) ? 'text-success-600' : ''">
              ✓ One special character
            </li>
          </ul>

          <Action
            label="Set new password"
            color="primary"
            variant="filled"
            size="lg"
            :disabled="form.processing"
            :loading="form.processing"
            @click="submit"
            class="w-full"
            type="submit"
          />
        </form>
      </div>

      <p class="mt-6 text-center text-sm text-gray-600">
        <a :href="panelUrl('/login')" class="font-medium text-primary-600 hover:text-primary-700">Back to sign in</a>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { panelUrl } from '../../Utils/url'
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import TextField from '../Fields/TextField.vue'
import Action from '../Actions/Action.vue'

const props = defineProps({
  valid: Boolean,
  token: String,
  email: String,
  csrf_token: String,
  errors: Object,
})

const form = useForm({
  _token: props.csrf_token,
  password: '',
  password_confirm: '',
})

// Merge server-side errors with Inertia form errors
const passwordError = computed(() => form.errors.password || props.errors?.password?.[0])

function submit() {
  form.post(panelUrl(`/reset-password/${props.token}`))
}
</script>
