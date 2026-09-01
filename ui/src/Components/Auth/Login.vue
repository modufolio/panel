<template>
  <Head title="Login" />
  <div class="flex items-center justify-center p-6 min-h-screen bg-gray-50">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="mb-8 text-center">
        <!-- Consumers supply their own mark. -->
        <slot name="logo" />
      </div>

      <!-- Flash Messages -->
      <FlashMessages class="mb-4" />

      <!-- Login Card -->
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5">
        <form @submit.prevent="login" class="p-8 space-y-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-950">Sign in</h2>
            <p class="mt-1 text-sm text-gray-600">Welcome back! Please enter your details.</p>
          </div>

          <!-- Email Field -->
          <TextField
            v-model="form.email"
            :error="form.errors.email"
            label="Email"
            type="email"
            autocomplete="email"
            required
          />

          <!-- Password Field -->
          <TextField
            v-model="form.password"
            :error="form.errors.password"
            label="Password"
            type="password"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          />

          <!-- Remember me -->
          <CheckboxField
            v-model="form.remember"
            label="Remember me"
            description="Stay signed in on this device for 30 days."
          />

          <!-- Submit Button -->
          <Action
            label="Sign in"
            color="primary"
            variant="filled"
            size="lg"
            :disabled="form.processing"
            :loading="form.processing"
            @click="login"
            class="w-full"
            type="submit"
          />

          <!-- Sign in with Google (shown only when configured server-side) -->
          <template v-if="google_enabled">
            <div class="relative">
              <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-gray-200" />
              </div>
              <div class="relative flex justify-center text-sm">
                <span class="bg-white px-2 text-gray-500">or</span>
              </div>
            </div>

            <a
              :href="panelUrl('/auth/google/start')"
              class="flex w-full items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/>
              </svg>
              Sign in with Google
            </a>
          </template>

          <!-- Forgot password -->
          <p class="text-center text-sm text-gray-600">
            <a :href="panelUrl('/forgot-password')" class="font-medium text-primary-600 hover:text-primary-700">
              Forgot your password?
            </a>
          </p>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/* empty */
import { Head, useForm } from '@inertiajs/vue3'
import { panelUrl } from '../../Utils/url'
import FlashMessages from '../../Components/Core/FlashMessages.vue'
import CheckboxField from '../../Components/Fields/CheckboxField.vue'
import TextField from '../Fields/TextField.vue'
import Action from '../Actions/Action.vue'

// Define props
const props = defineProps({
  csrf_token: {
    type: String,
    required: true
  },
  // Server sets this only when the Google OAuth client is configured.
  google_enabled: {
    type: Boolean,
    default: false
  }
})

// Create form using Inertia's useForm composable
const form = useForm({
  email: '',
  password: '',
  remember: false,
  _csrf_token: props.csrf_token,
})

// Login method
const login = () => {
  form.post(panelUrl('/login'))
}
</script>