<template>
  <Dialog
    :is-open="warning"
    title="You’re about to be signed out"
    width="sm"
    @close="staySignedIn"
  >
    <!-- The visible countdown changes every second, which a screen reader
         would otherwise announce every second. It is hidden from assistive
         tech, and the live region below carries the same message rounded to
         20-second steps — the approach HMRC's timeout dialog uses. -->
    <p class="text-sm text-ink-2" aria-hidden="true">
      For your security, we’ll sign you out in
      <span class="font-semibold text-ink tabular-nums">{{ humanRemaining }}</span>.
    </p>
    <p class="sr-only" aria-live="assertive">{{ announcement }}</p>

    <template #footer>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="rounded-lg px-4 py-2 text-sm font-medium text-ink-2 hover:bg-hover transition-colors"
          :disabled="extending"
          @click="signOut"
        >
          Sign out
        </button>
        <button
          ref="stayButton"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-primary-fill px-4 py-2 text-sm font-medium text-primary-on-fill hover:bg-primary-hover disabled:opacity-50 transition-colors"
          :disabled="extending"
          @click="staySignedIn"
        >
          Stay signed in
        </button>
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
/**
 * Warns before the server's idle timeout signs the user out, and offers to
 * extend. Renders nothing until the warning window opens.
 *
 * WCAG 2.2 SC 2.2.1 (Level A) applies even though the limit exists for
 * security: the user must be warned, get at least 20 seconds to respond with
 * a simple action, and be able to extend at least ten times. Hence a warning
 * measured in minutes, a single button, and no cap on extending.
 */
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Dialog from './Dialog.vue'
import { useSessionTimeout } from '../../Composables/useSessionTimeout'
import { panelUrl } from '../../Utils/url'

const props = withDefaults(defineProps<{
  /** Seconds of inactivity the server allows. */
  timeout: number
  /** Seconds before expiry that this dialog appears. */
  warnBefore?: number
  /** POSTed to extend the session; any ordinary path would do. */
  extendUrl?: string
  /** Where the browser lands once the session is gone. */
  expiredUrl?: string
}>(), {
  warnBefore: 120,
  extendUrl: '/session/extend',
  expiredUrl: '/login',
})

const page = usePage()
const extending = ref(false)
const stayButton = ref<HTMLButtonElement | null>(null)

const { remaining, warning, activity, check } = useSessionTimeout({
  timeout: props.timeout,
  warnBefore: props.warnBefore,
  onExpire: () => {
    // A full page visit, not an Inertia one: the session is gone, so the
    // panel shell must be replaced by the login page rather than left on
    // screen around a dead SPA.
    window.location.href = panelUrl(props.expiredUrl)
  },
})

// Every completed Inertia navigation is activity — the same requests that
// slide the deadline on the server. The fresh `session.remaining` prop comes
// back with the response, so the two clocks agree without any polling.
router.on('success', () => {
  const session = (page.props as { session?: { remaining?: number } }).session
  activity(typeof session?.remaining === 'number' ? session.remaining : props.timeout)
})

const humanRemaining = computed(() => {
  const seconds = remaining.value
  if (seconds >= 60) {
    const minutes = Math.ceil(seconds / 60)
    return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'}`
  }
  return `${seconds} ${seconds === 1 ? 'second' : 'seconds'}`
})

/**
 * The spoken message, rounded up to 20-second steps under a minute, so the
 * live region speaks a handful of times instead of once per second.
 */
const announcement = computed(() => {
  if (!warning.value) return ''

  const seconds = remaining.value
  const rounded = seconds >= 60 ? seconds : Math.max(20, Math.ceil(seconds / 20) * 20)
  const label = rounded >= 60
    ? `${Math.ceil(rounded / 60)} ${Math.ceil(rounded / 60) === 1 ? 'minute' : 'minutes'}`
    : `${rounded} seconds`

  return `For your security, we’ll sign you out in ${label}.`
})

watch(warning, async (isWarning) => {
  if (!isWarning) return
  await Promise.resolve()
  stayButton.value?.focus()
})

const staySignedIn = async (): Promise<void> => {
  if (extending.value) return
  extending.value = true

  try {
    const response = await fetch(panelUrl(props.extendUrl), {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': (page.props as { csrf_token?: string }).csrf_token ?? '',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) {
      // Most likely already expired server-side; let the countdown reach zero
      // and redirect rather than pretending the extension worked.
      check()
      return
    }

    const data = await response.json() as { remaining?: number }
    activity(typeof data.remaining === 'number' ? data.remaining : props.timeout)
  } catch {
    check()
  } finally {
    extending.value = false
  }
}

const signOut = (): void => {
  router.post(panelUrl('/logout'), {
    _csrf_token: (page.props as { logout_csrf?: string }).logout_csrf ?? '',
  })
}
</script>
