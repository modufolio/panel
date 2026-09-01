<template>
  <div>
    <Head title="Two-Factor Authentication" />

    <!-- Page Header with Breadcrumbs -->
    <PageHeader title="Two-Factor Authentication"/>

    <!-- 2FA Status Card -->
    <div class="max-w-3xl">
      <Section
        heading="Two-Factor Authentication Status"
        :description="isEnabled ? 'Two-factor authentication is currently enabled for your account.' : 'Two-factor authentication is not enabled. Enable it to add an extra layer of security.'"
      >
        <div class="space-y-6">
          <!-- Status Badge -->
          <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center gap-3">
              <div :class="[
                'flex items-center justify-center w-10 h-10 rounded-full',
                isEnabled ? 'bg-success-100' : 'bg-warning-100'
              ]">
                <svg v-if="isEnabled" class="w-5 h-5 text-success-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else class="w-5 h-5 text-warning-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
              </div>
              <div>
                <h3 class="text-sm font-semibold text-gray-900">
                  {{ isEnabled ? 'Enabled' : 'Disabled' }}
                </h3>
                <p class="text-xs text-gray-600">
                  {{ isEnabled ? 'Your account is protected with 2FA' : 'Your account is not protected with 2FA' }}
                </p>
              </div>
            </div>
            <div>
              <Action
                v-if="!isEnabled"
                label="Enable 2FA"
                color="primary"
                @click="setupTwoFactor"
                :loading="setupForm.processing"
              />
              <Action
                v-else
                label="Disable 2FA"
                color="danger"
                variant="outlined"
                @click="confirmDisable"
                :loading="disableForm.processing"
              />
            </div>
          </div>

          <!-- Enabled Date -->
          <div v-if="isEnabled && status.enabled_at" class="text-sm text-gray-600">
            <span class="font-medium">Enabled on:</span> {{ formatDate(status.enabled_at) }}
          </div>

          <!-- Backup Codes Section -->
          <div v-if="isEnabled">
            <div class="border-t border-gray-200 pt-6">
              <h4 class="text-sm font-semibold text-gray-900 mb-2">Backup Codes</h4>
              <p class="text-sm text-gray-600 mb-4">
                Backup codes can be used to access your account if you lose access to your authenticator app.
                Store them in a safe place.
              </p>
              <Action
                label="View Backup Codes"
                color="gray"
                variant="outlined"
                size="sm"
                @click="showBackupCodesDialog = true"
              />
            </div>
          </div>
        </div>
      </Section>

      <!-- How it Works Section -->
      <Section
        v-if="!isEnabled"
        class="mt-6"
        heading="How it Works"
        description="Two-factor authentication adds an extra layer of security to your account."
      >
        <div class="space-y-4">
          <div class="flex gap-4">
            <div class="shrink-0">
              <div class="flex items-center justify-center w-8 h-8 bg-primary-100 rounded-full text-primary-600 font-semibold text-sm">
                1
              </div>
            </div>
            <div>
              <h5 class="text-sm font-semibold text-gray-900">Install an Authenticator App</h5>
              <p class="text-sm text-gray-600 mt-1">
                Download and install Google Authenticator, Authy, or any TOTP-compatible app on your mobile device.
              </p>
            </div>
          </div>

          <div class="flex gap-4">
            <div class="shrink-0">
              <div class="flex items-center justify-center w-8 h-8 bg-primary-100 rounded-full text-primary-600 font-semibold text-sm">
                2
              </div>
            </div>
            <div>
              <h5 class="text-sm font-semibold text-gray-900">Scan QR Code</h5>
              <p class="text-sm text-gray-600 mt-1">
                When you enable 2FA, scan the QR code with your authenticator app to add your account.
              </p>
            </div>
          </div>

          <div class="flex gap-4">
            <div class="shrink-0">
              <div class="flex items-center justify-center w-8 h-8 bg-primary-100 rounded-full text-primary-600 font-semibold text-sm">
                3
              </div>
            </div>
            <div>
              <h5 class="text-sm font-semibold text-gray-900">Enter Verification Code</h5>
              <p class="text-sm text-gray-600 mt-1">
                Each time you log in, you'll need to enter a 6-digit code from your authenticator app.
              </p>
            </div>
          </div>

          <div class="flex gap-4">
            <div class="shrink-0">
              <div class="flex items-center justify-center w-8 h-8 bg-primary-100 rounded-full text-primary-600 font-semibold text-sm">
                4
              </div>
            </div>
            <div>
              <h5 class="text-sm font-semibold text-gray-900">Save Backup Codes</h5>
              <p class="text-sm text-gray-600 mt-1">
                Store your backup codes in a safe place. You can use them to access your account if you lose your device.
              </p>
            </div>
          </div>
        </div>
      </Section>
    </div>

    <!-- Setup Dialog -->
    <Dialog
      :is-open="showSetupDialog"
      @close="closeSetupDialog"
      title="Enable Two-Factor Authentication"
      width="2xl"
    >
      <div v-if="setupStep === 'qr'" class="space-y-6">
        <!-- QR Code Display -->
        <div class="text-center">
          <div class="inline-block p-4 bg-white rounded-lg border-2 border-gray-200">
            <img v-if="qrCode" :src="qrCode" alt="QR Code" class="w-64 h-64" />
            <div v-else class="w-64 h-64 flex items-center justify-center">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
            </div>
          </div>
          <p class="mt-4 text-sm text-gray-600">
            Scan this QR code with your authenticator app
          </p>
        </div>

        <!-- Manual Entry Option -->
        <div class="bg-gray-50 rounded-lg p-4">
          <h4 class="text-sm font-semibold text-gray-900 mb-2">Can't scan the code?</h4>
          <p class="text-xs text-gray-600 mb-2">Enter this secret key manually in your authenticator app:</p>
          <!--
            min-w-0 lets the code block shrink below its content width: a
            base32 secret is one unbreakable token, and a flex item's default
            min-width:auto would otherwise push the Copy button out of the
            dialog. break-all wraps it; shrink-0 keeps the button intact.
          -->
          <div class="flex items-start gap-2">
            <code class="min-w-0 flex-1 px-3 py-2 bg-white border border-gray-300 rounded text-sm font-mono break-all">
              {{ secret }}
            </code>
            <button
              type="button"
              @click="copySecret"
              class="shrink-0 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 transition-colors"
            >
              {{ secretCopied ? 'Copied!' : 'Copy' }}
            </button>
          </div>
        </div>

        <!-- Verification Form -->
        <form @submit.prevent="verifyAndEnable">
          <TextField
            v-model="enableForm.code"
            label="Verification Code"
            placeholder="Enter 6-digit code from your app"
            :error="enableForm.errors.code"
            required
            autofocus
          />

          <div class="mt-6 flex items-center justify-end gap-3">
            <Action
              label="Cancel"
              color="gray"
              variant="outlined"
              @click="closeSetupDialog"
              :disabled="enableForm.processing"
            />
            <Action
              label="Enable 2FA"
              color="primary"
              type="submit"
              :loading="enableForm.processing"
            />
          </div>
        </form>
      </div>

      <!-- Backup Codes Display -->
      <div v-else-if="setupStep === 'backup'" class="space-y-6">
        <div class="bg-warning-50 border border-warning-200 rounded-lg p-4">
          <div class="flex gap-3">
            <svg class="w-5 h-5 text-warning-600 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
              <h4 class="text-sm font-semibold text-warning-900">Save These Backup Codes</h4>
              <p class="text-sm text-warning-700 mt-1">
                Store these backup codes in a safe place. Each code can only be used once.
                You won't be able to see them again after closing this dialog.
              </p>
            </div>
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
          <div class="grid grid-cols-2 gap-3">
            <code
              v-for="(code, index) in backupCodes"
              :key="index"
              class="px-3 py-2 bg-white border border-gray-300 rounded text-sm font-mono text-center"
            >
              {{ code }}
            </code>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3">
          <Action
            label="Copy All Codes"
            color="gray"
            variant="outlined"
            @click="copyBackupCodes"
          />
          <Action
            label="I've Saved My Codes"
            color="primary"
            @click="finishSetup"
          />
        </div>
      </div>
    </Dialog>

    <!-- Backup Codes View Dialog -->
    <Dialog
      :is-open="showBackupCodesDialog"
      @close="showBackupCodesDialog = false"
      title="Backup Codes"
      width="md"
    >
      <div class="space-y-4">
        <div class="bg-info-50 border border-info-200 rounded-lg p-4">
          <div class="flex gap-3">
            <svg class="w-5 h-5 text-info-600 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
            </svg>
            <div>
              <p class="text-sm text-info-700">
                For security reasons, we don't store your backup codes in plain text.
                You'll need to regenerate new codes if you've lost the original ones.
              </p>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end">
          <Action
            label="Regenerate Backup Codes"
            color="primary"
            @click="regenerateBackupCodes"
            :loading="regenerateForm.processing"
          />
        </div>
      </div>
    </Dialog>

    <!-- Disable Confirmation Dialog -->
    <Dialog
      :is-open="showDisableDialog"
      @close="showDisableDialog = false"
      title="Disable Two-Factor Authentication"
      width="md"
    >
      <div class="space-y-4">
        <div class="bg-danger-50 border border-danger-200 rounded-lg p-4">
          <div class="flex gap-3">
            <svg class="w-5 h-5 text-danger-600 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
              <h4 class="text-sm font-semibold text-danger-900">Warning</h4>
              <p class="text-sm text-danger-700 mt-1">
                Disabling two-factor authentication will make your account less secure.
                Are you sure you want to continue?
              </p>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3">
          <Action
            label="Cancel"
            color="gray"
            variant="outlined"
            @click="showDisableDialog = false"
            :disabled="disableForm.processing"
          />
          <Action
            label="Yes, Disable 2FA"
            color="danger"
            @click="disableTwoFactor"
            :loading="disableForm.processing"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useToast } from '../Notifications/useToast'
import Section from '../Sections/Section.vue'
import PageHeader from '../Layout/PageHeader.vue'
import Action from '../Actions/Action.vue'
import TextField from '../Fields/TextField.vue'
import Dialog from '../Dialogs/Dialog.vue'
import { panelUrl } from '../../Utils/url'
import { apiFetch, ApiError } from '../../Utils/apiFetch'

/** Payload of the setup endpoint: what the authenticator app is shown. */
interface SetupResponse {
  qr_code: string | null
  secret: string | null
}

/** Payload of the endpoints that mint recovery codes. */
interface BackupCodesResponse {
  backup_codes?: string[]
}

/** The `{error, errors}` shape the 2FA endpoints answer failures with. */
interface ErrorBody {
  error?: string
  errors?: Record<string, string[]>
}

/** The parsed error payload, when the failure came from the API in that shape. */
const errorBodyOf = (error: unknown): ErrorBody | null =>
  error instanceof ApiError && error.body !== null && typeof error.body === 'object'
    ? (error.body as ErrorBody)
    : null

// The persistent layout is the consuming app's choice; assign it where the
// page is registered (e.g. `TwoFactor.layout = AppLayout`) rather than
// hardcoding one here.

const props = defineProps({
  enabled: {
    type: Boolean,
    required: true,
  },
  confirmed: {
    type: Boolean,
    default: false,
  },
  enabled_at: {
    type: String,
    default: null,
  },
})

const toast = useToast()

// Create status object for backward compatibility
const status = computed(() => ({
  enabled: props.enabled,
  confirmed: props.confirmed,
  enabled_at: props.enabled_at,
}))

// Computed properties
const isEnabled = computed(() => props.enabled === true)

// Dialog states
const showSetupDialog = ref(false)
const showBackupCodesDialog = ref(false)
const showDisableDialog = ref(false)
const setupStep = ref('qr') // 'qr' or 'backup'

// Setup data
const qrCode = ref<string | null>(null)
const secret = ref<string | null>(null)
const backupCodes = ref<string[]>([])
const secretCopied = ref(false)

// Forms
const setupForm = useForm({})
const enableForm = useForm({
  code: '',
})
const disableForm = useForm({})
const regenerateForm = useForm({})

// Setup Two-Factor
const setupTwoFactor = async () => {
  setupForm.processing = true

  try {
    const data = await apiFetch<SetupResponse>(panelUrl('/api/2fa/setup'), { method: 'POST' })

    qrCode.value = data.qr_code
    secret.value = data.secret
    setupStep.value = 'qr'
    showSetupDialog.value = true

  } catch (error) {
    const message = errorBodyOf(error)?.error ?? null
    toast.error(message || 'Failed to setup two-factor authentication')
  } finally {
    setupForm.processing = false
  }
}

// Verify and Enable
const verifyAndEnable = async () => {
  enableForm.processing = true
  enableForm.clearErrors()

  try {
    const data = await apiFetch<BackupCodesResponse>(panelUrl('/api/2fa/enable'), {
      method: 'POST',
      body: { code: enableForm.code },
    })

    backupCodes.value = data.backup_codes || []
    setupStep.value = 'backup'
    toast.success('Two-factor authentication enabled successfully!')

  } catch (error) {
    const codeErrors = errorBodyOf(error)?.errors?.code
    if (codeErrors) {
      enableForm.setError('code', codeErrors[0])
    } else {
      const message = errorBodyOf(error)?.error ?? null
      toast.error(message || 'Failed to enable two-factor authentication')
    }
  } finally {
    enableForm.processing = false
  }
}

// Finish Setup
const finishSetup = () => {
  closeSetupDialog()
  router.reload({ only: ['enabled', 'confirmed', 'enabled_at'] })
}

// Close Setup Dialog
const closeSetupDialog = () => {
  showSetupDialog.value = false
  setupStep.value = 'qr'
  qrCode.value = null
  secret.value = null
  backupCodes.value = []
  enableForm.reset()
  enableForm.clearErrors()
}

// Copy Secret
const copySecret = () => {
  navigator.clipboard.writeText(secret.value ?? '')
  secretCopied.value = true
  toast.success('Secret key copied to clipboard')
  setTimeout(() => {
    secretCopied.value = false
  }, 2000)
}

// Copy Backup Codes
const copyBackupCodes = () => {
  const codesText = backupCodes.value.join('\n')
  navigator.clipboard.writeText(codesText)
  toast.success('Backup codes copied to clipboard')
}

// Confirm Disable
const confirmDisable = () => {
  showDisableDialog.value = true
}

// Disable Two-Factor
const disableTwoFactor = async () => {
  disableForm.processing = true

  try {
    await apiFetch(panelUrl('/api/2fa/disable'), { method: 'POST' })

    showDisableDialog.value = false
    toast.success('Two-factor authentication disabled')
    router.reload({ only: ['enabled', 'confirmed', 'enabled_at'] })

  } catch (error) {
    const message = errorBodyOf(error)?.error ?? null
    toast.error(message || 'Failed to disable two-factor authentication')
  } finally {
    disableForm.processing = false
  }
}

// Regenerate Backup Codes
const regenerateBackupCodes = async () => {
  regenerateForm.processing = true

  try {
    const data = await apiFetch<BackupCodesResponse>(panelUrl('/api/2fa/regenerate-backup-codes'), { method: 'POST' })

    backupCodes.value = data.backup_codes || []
    showBackupCodesDialog.value = false
    setupStep.value = 'backup'
    showSetupDialog.value = true
    toast.success('Backup codes regenerated')

  } catch (error) {
    const message = errorBodyOf(error)?.error ?? null
    toast.error(message || 'Failed to regenerate backup codes')
  } finally {
    regenerateForm.processing = false
  }
}

// Format Date
const formatDate = (dateString: string) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>
