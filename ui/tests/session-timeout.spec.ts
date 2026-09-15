import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import { useSessionTimeout } from '../src/Composables/useSessionTimeout'

/**
 * Mount inside a component so onUnmounted cleanup runs, and hand the harness
 * back so a test can unmount deterministically.
 */
function withTimeout(options: Parameters<typeof useSessionTimeout>[0]) {
  let api!: ReturnType<typeof useSessionTimeout>

  const wrapper = mount(defineComponent({
    setup() {
      api = useSessionTimeout(options)
      return () => null
    },
  }))

  return { api, wrapper }
}

describe('useSessionTimeout', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-09-14T12:00:00Z'))
    localStorage.clear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('counts down and opens the warning at the configured lead time', async () => {
    const { api, wrapper } = withTimeout({ timeout: 900, warnBefore: 120 })

    expect(api.remaining.value).toBe(900)
    expect(api.warning.value).toBe(false)

    // One second before the warning window.
    await vi.advanceTimersByTimeAsync((900 - 121) * 1000)
    expect(api.warning.value).toBe(false)
    expect(api.remaining.value).toBe(121)

    await vi.advanceTimersByTimeAsync(1000)
    expect(api.warning.value).toBe(true)
    expect(api.remaining.value).toBe(120)

    wrapper.unmount()
  })

  it('expires once, at zero, and never reports a negative remaining', async () => {
    const onExpire = vi.fn()
    const { api, wrapper } = withTimeout({ timeout: 60, warnBefore: 30, onExpire })

    await vi.advanceTimersByTimeAsync(59_000)
    expect(onExpire).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1000)
    expect(onExpire).toHaveBeenCalledTimes(1)
    expect(api.expired.value).toBe(true)

    // Well past the deadline: still 0, still only one call.
    await vi.advanceTimersByTimeAsync(600_000)
    expect(api.remaining.value).toBe(0)
    expect(onExpire).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })

  /**
   * The sleep case. Timers do not fire while a machine is suspended, so a
   * counter that decremented per tick would wake up minutes behind the server
   * and show time the session no longer has. Remaining is derived from the
   * wall clock, so it jumps instead of drifting.
   */
  it('reads the wall clock rather than trusting the timer', async () => {
    const onExpire = vi.fn()
    const { api, wrapper } = withTimeout({ timeout: 900, warnBefore: 120, onExpire })

    // Ten minutes pass with no timer callbacks at all, as when a laptop sleeps.
    vi.setSystemTime(Date.now() + 600_000)
    await vi.advanceTimersByTimeAsync(1000)

    expect(api.remaining.value).toBe(299)
    expect(onExpire).not.toHaveBeenCalled()

    // Sleeping clean through the deadline expires on the next tick.
    vi.setSystemTime(Date.now() + 600_000)
    await vi.advanceTimersByTimeAsync(1000)

    expect(api.remaining.value).toBe(0)
    expect(onExpire).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })

  /**
   * A hidden tab has its timers throttled to roughly once a minute, so the
   * tick that opens the warning may not run at all while the user is away.
   * Becoming visible re-checks immediately rather than waiting out the next
   * tick — otherwise a tab returned to after a break would show stale time.
   */
  describe('a tab that was hidden', () => {
    const setVisibility = (state: 'visible' | 'hidden') => {
      Object.defineProperty(document, 'visibilityState', { value: state, configurable: true })
      document.dispatchEvent(new Event('visibilitychange'))
    }

    afterEach(() => setVisibility('visible'))

    it('opens the warning as soon as it becomes visible', async () => {
      const { api, wrapper } = withTimeout({ timeout: 900, warnBefore: 120 })

      // Time passes with no ticks, as a throttled background tab sees it.
      setVisibility('hidden')
      vi.setSystemTime(Date.now() + 800_000)
      expect(api.warning.value).toBe(false)

      setVisibility('visible')

      expect(api.warning.value).toBe(true)
      expect(api.remaining.value).toBe(100)

      wrapper.unmount()
    })

    it('expires on becoming visible when the deadline passed while away', async () => {
      const onExpire = vi.fn()
      const { api, wrapper } = withTimeout({ timeout: 900, warnBefore: 120, onExpire })

      setVisibility('hidden')
      vi.setSystemTime(Date.now() + 1_000_000)
      expect(onExpire).not.toHaveBeenCalled()

      setVisibility('visible')

      expect(onExpire).toHaveBeenCalledTimes(1)
      expect(api.remaining.value).toBe(0)

      wrapper.unmount()
    })
  })

  it('activity pushes the deadline out and closes the warning', async () => {
    const { api, wrapper } = withTimeout({ timeout: 900, warnBefore: 120 })

    await vi.advanceTimersByTimeAsync((900 - 60) * 1000)
    expect(api.warning.value).toBe(true)

    api.activity(900)
    await vi.advanceTimersByTimeAsync(1000)

    expect(api.warning.value).toBe(false)
    expect(api.remaining.value).toBe(899)

    wrapper.unmount()
  })

  it('ignores activity after expiry, so a late response cannot resurrect a dead session', async () => {
    const { api, wrapper } = withTimeout({ timeout: 60, warnBefore: 30 })

    await vi.advanceTimersByTimeAsync(60_000)
    expect(api.expired.value).toBe(true)

    api.activity(900)
    await vi.advanceTimersByTimeAsync(1000)

    expect(api.expired.value).toBe(true)
    expect(api.remaining.value).toBe(0)

    wrapper.unmount()
  })

  describe('across tabs', () => {
    it('publishes its deadline so other tabs can see it', () => {
      const { api, wrapper } = withTimeout({ timeout: 900, warnBefore: 120, storageKey: 'k' })

      api.activity(900)

      expect(Number(localStorage.getItem('k'))).toBe(Date.now() + 900_000)

      wrapper.unmount()
    })

    /**
     * Activity in another tab must dismiss this tab's warning — the server
     * deadline is shared, so the warning is wrong the moment any tab is used.
     * A stored timestamp survives a suspended tab, which a broadcast message
     * would not.
     */
    it('adopts a later deadline written by another tab', async () => {
      const { api, wrapper } = withTimeout({ timeout: 900, warnBefore: 120, storageKey: 'k' })

      await vi.advanceTimersByTimeAsync((900 - 60) * 1000)
      expect(api.warning.value).toBe(true)

      localStorage.setItem('k', String(Date.now() + 900_000))
      await vi.advanceTimersByTimeAsync(1000)

      expect(api.warning.value).toBe(false)
      expect(api.remaining.value).toBe(899)

      wrapper.unmount()
    })

    it('never adopts an earlier deadline than its own', async () => {
      const { api, wrapper } = withTimeout({ timeout: 900, warnBefore: 120, storageKey: 'k' })

      localStorage.setItem('k', String(Date.now() + 10_000))
      await vi.advanceTimersByTimeAsync(1000)

      expect(api.remaining.value).toBe(899)

      wrapper.unmount()
    })
  })

  it('works when localStorage throws, as in a private window', async () => {
    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied')
    })
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied')
    })

    const { api, wrapper } = withTimeout({ timeout: 900, warnBefore: 120 })

    api.activity(900)
    await vi.advanceTimersByTimeAsync(1000)

    expect(api.remaining.value).toBe(899)

    getItem.mockRestore()
    setItem.mockRestore()
    wrapper.unmount()
  })
})
