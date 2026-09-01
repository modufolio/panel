<template>
  <Head title="Forgot Password" />
  <div class="flex items-center justify-center p-6 min-h-screen bg-gray-50">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="mb-8 text-center">
        <!-- Consumers supply their own mark. -->
        <slot name="logo" />
      </div>

      <!-- Flash Messages -->
      <FlashMessages class="mb-4" />

      <!-- Card -->
      <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-950/5">
        <form @submit.prevent="submit" class="p-8 space-y-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-950">Reset your password</h2>
            <p class="mt-1 text-sm text-gray-600">
              Enter your email address and an administrator will review your request and send you a reset link.
            </p>
          </div>

          <TextField
            v-model="form.email"
            :error="form.errors.email"
            label="Email"
            type="email"
            autocomplete="email"
            required
          />

          <Action
            label="Request reset link"
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

      <!-- Back to login -->
      <p class="mt-6 text-center text-sm text-gray-600">
        Remember your password?
        <a :href="panelUrl('/login')" class="font-medium text-primary-600 hover:text-primary-700">Sign in</a>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { panelUrl } from '../../Utils/url'
import FlashMessages from '../../Components/Core/FlashMessages.vue'
import TextField from '../Fields/TextField.vue'
import Action from '../Actions/Action.vue'

const props = defineProps({
  csrf_token: String,
})

const form = useForm({
  _token: props.csrf_token,
  email: '',
})

function submit() {
  form.post(panelUrl('/forgot-password'))
}
</script>
