<template>
  <Head title="Two-Factor Authentication" />
  <div class="flex items-center justify-center p-6 min-h-screen bg-gray-50">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="mb-8 text-center">
        <!-- Consumers supply their own mark. -->
        <slot name="logo" />
      </div>

      <!-- 2FA Card -->
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5">
        <form @submit.prevent="verify" class="p-8 space-y-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-950">
              Two-Factor Authentication
            </h2>
            <p class="mt-1 text-sm text-gray-600">
              Please enter your authentication code
            </p>
          </div>

          <!-- Info Banner -->
          <div class="bg-primary-50 border border-primary-200 rounded-lg p-4">
            <div class="flex gap-3">
              <svg class="w-5 h-5 text-primary-600 flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
              </svg>
              <div>
                <p class="text-sm text-primary-700">
                  Open your authenticator app and enter the 6-digit code.
                </p>
                <p class="text-xs text-primary-600 mt-1">
                  Logging in as <strong>{{ email }}</strong>
                </p>
              </div>
            </div>
          </div>

          <!-- TOTP Code Field -->
          <template v-if="!showBackupCodeInput">
            <TextField
              v-model="form.totp_code"
              :error="form.errors.totp_code"
              label="Authentication Code"
              type="text"
              placeholder="000000"
              autocomplete="one-time-code"
              inputmode="numeric"
              pattern="[0-9]*"
              maxlength="6"
              required
              autofocus
            />
          </template>

          <!-- Backup Code Field -->
          <template v-else>
            <TextField
              v-model="form.backup_code"
              :error="form.errors.backup_code"
              label="Backup Code"
              type="text"
              placeholder="XXXX-XXXX"
              autocomplete="off"
              required
              autofocus
            />
          </template>

          <!-- Toggle Backup Code -->
          <div class="text-sm">
            <button
              type="button"
              @click="showBackupCodeInput = !showBackupCodeInput"
              class="text-primary-600 hover:text-primary-700 font-medium"
            >
              {{ showBackupCodeInput ? 'Use authenticator code' : 'Use backup code instead' }}
            </button>
          </div>

          <!-- Action Buttons -->
          <div class="flex gap-3">
            <Action
              label="Back to Login"
              color="gray"
              variant="outlined"
              size="lg"
              @click="goBack"
              class="flex-1"
              type="button"
            />
            <Action
              label="Verify"
              color="primary"
              variant="filled"
              size="lg"
              :disabled="form.processing"
              :loading="form.processing"
              class="flex-1"
              type="submit"
            />
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { panelUrl } from '../../Utils/url'
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import TextField from '../Fields/TextField.vue'
import Action from '../Actions/Action.vue'

// Define props
const props = defineProps({
  email: {
    type: String,
    required: true
  },
  csrf_token: {
    type: String,
    required: true
  }
})

// State
const showBackupCodeInput = ref(false)

// Create form
const form = useForm({
  totp_code: '',
  backup_code: '',
  _csrf_token: props.csrf_token,
})

// Verify method
const verify = () => {
  // Clear the code that's not being used
  if (showBackupCodeInput.value) {
    form.totp_code = ''
  } else {
    form.backup_code = ''
  }

  form.post(panelUrl('/2fa'), {
    onError: (errors) => {
      // Handle errors
      console.error('2FA verification errors:', errors)
    }
  })
}

// Go back to login
const goBack = () => {
  router.visit(panelUrl('/login'))
}
</script>
