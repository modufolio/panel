import { computed, onUnmounted, readonly, ref, type Ref } from 'vue'

/**
 * Counts down to the server's idle-session deadline and reports when the
 * warning dialog should be up.
 *
 * Two decisions worth knowing about:
 *
 * **Wall clock, not timers.** `remaining` is always recomputed as
 * `deadline - Date.now()`, never decremented. A `setTimeout` does not fire
 * while a laptop sleeps, so a decremented counter wakes up minutes behind the
 * server and would happily show "9 minutes left" on a session that has already
 * gone. The tick only decides *when to look*, never what the answer is.
 *
 * **No polling.** The deadline is refreshed by `activity()`, which the panel
 * calls when a real request completes — the same thing that slides the
 * deadline server-side. Nothing here asks the server what time it is, because
 * asking is itself a request: a heartbeat would renew the very session it is
 * reporting on and the timeout would never fire.
 */
export interface SessionTimeoutOptions {
  /** Seconds of inactivity the server allows. */
  timeout: number
  /** Seconds before expiry that the warning appears. */
  warnBefore: number
  /** Called once when the deadline passes. */
  onExpire?: () => void
  /**
   * Key used to share the deadline between tabs, via localStorage. A shared
   * timestamp beats broadcasting messages: a suspended tab misses messages,
   * but reads the timestamp correctly the moment it wakes.
   */
  storageKey?: string
}

export function useSessionTimeout(options: SessionTimeoutOptions) {
  const { timeout, warnBefore, onExpire, storageKey = 'panel:session-deadline' } = options

  const deadline = ref(Date.now() + timeout * 1000)
  const now = ref(Date.now())
  const expired = ref(false)

  const remaining = computed(() => Math.max(0, Math.round((deadline.value - now.value) / 1000)))
  const warning = computed(() => !expired.value && remaining.value <= warnBefore)

  const readShared = (): number | null => {
    try {
      const stored = Number(localStorage.getItem(storageKey))
      return Number.isFinite(stored) && stored > 0 ? stored : null
    } catch {
      // Private mode, or storage disabled. Single-tab behaviour is still correct.
      return null
    }
  }

  const writeShared = (value: number): void => {
    try {
      localStorage.setItem(storageKey, String(value))
    } catch { /* see readShared */ }
  }

  /**
   * A real request happened: push the deadline out, and tell the other tabs.
   * Ignored once expired, so a late in-flight response cannot resurrect a
   * session the server has already dropped.
   */
  const activity = (secondsLeft: number = timeout): void => {
    if (expired.value) return

    deadline.value = Date.now() + secondsLeft * 1000
    now.value = Date.now()
    writeShared(deadline.value)
  }

  const check = (): void => {
    // Another tab may have moved the deadline further out than ours.
    const shared = readShared()
    if (shared !== null && shared > deadline.value) {
      deadline.value = shared
    }

    now.value = Date.now()

    if (!expired.value && deadline.value <= now.value) {
      expired.value = true
      onExpire?.()
    }
  }

  // One second while the warning is plausible, otherwise lazily: there is
  // nothing to render until the last `warnBefore` seconds, and a panel left
  // open all day should not wake up 28,800 times to find that out.
  let timer: ReturnType<typeof setInterval> | null = null

  const start = (): void => {
    if (timer !== null) return
    timer = setInterval(check, 1000)
  }

  const stop = (): void => {
    if (timer === null) return
    clearInterval(timer)
    timer = null
  }

  const onStorage = (event: StorageEvent): void => {
    if (event.key === storageKey) check()
  }

  const onVisible = (): void => {
    // Coming back from a background tab or a sleeping machine: re-read the
    // clock immediately rather than waiting for the next tick.
    if (document.visibilityState === 'visible') check()
  }

  if (typeof window !== 'undefined') {
    // Loading the page was itself a request, so this deadline is the newest
    // one any tab has: publish it rather than waiting for the first visit.
    writeShared(deadline.value)
    window.addEventListener('storage', onStorage)
    document.addEventListener('visibilitychange', onVisible)
    start()
  }

  onUnmounted(() => {
    stop()
    if (typeof window === 'undefined') return
    window.removeEventListener('storage', onStorage)
    document.removeEventListener('visibilitychange', onVisible)
  })

  return {
    /** Whole seconds until the session expires; never negative. */
    remaining: readonly(remaining) as Readonly<Ref<number>>,
    /** True once inside the warning window and not yet expired. */
    warning: readonly(warning) as Readonly<Ref<boolean>>,
    expired: readonly(expired) as Readonly<Ref<boolean>>,
    activity,
    check,
    stop,
  }
}
