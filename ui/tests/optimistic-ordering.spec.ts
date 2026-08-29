import { beforeEach, describe, expect, it } from 'vitest'
import { optimistic } from '../src/Utils/optimistic'
import { writeGate } from '../src/Utils/writeGate'

/** A promise settled by hand, so tests control the order responses land in. */
function deferred(): { promise: Promise<void>; resolve: () => void; reject: () => void } {
  let resolve!: () => void
  let reject!: () => void
  const promise = new Promise<void>((res, rej) => {
    resolve = res
    reject = () => rej(new Error('request failed'))
  })

  return { promise, resolve, reject }
}

describe('optimistic', () => {
  beforeEach(() => {
    writeGate.clear()
  })

  it('applies immediately and keeps the change on success', async () => {
    const state = { flag: false }
    const request = deferred()

    const result = optimistic(
      () => {
        state.flag = true

        return () => { state.flag = false }
      },
      () => request.promise,
      'media/1/flag',
    )

    expect(state.flag).toBe(true)
    request.resolve()
    await expect(result).resolves.toBe(true)
    expect(state.flag).toBe(true)
  })

  it('rolls back on failure when no later mutation intervened', async () => {
    const state = { flag: false }
    const request = deferred()

    const result = optimistic(
      () => {
        state.flag = true

        return () => { state.flag = false }
      },
      () => request.promise,
      'media/1/flag',
    )

    request.reject()
    await expect(result).resolves.toBe(false)
    expect(state.flag).toBe(false)
  })

  /**
   * The interleaving bug this file exists for: toggle, toggle again while the
   * first request is in flight, first request fails. The stale rollback must
   * NOT fire — it would restore the value from before either toggle, erasing
   * the second toggle's applied change while its request succeeds.
   */
  it('skips a stale rollback once a later mutation owns the state', async () => {
    const state = { flag: false }
    const key = 'media/1/flag'
    const first = deferred()
    const second = deferred()

    const toggle = (request: Promise<void>) =>
      optimistic(
        () => {
          const previous = state.flag
          state.flag = !previous

          return () => { state.flag = previous }
        },
        () => request,
        key,
      )

    const firstResult = toggle(first.promise)   // false -> true
    const secondResult = toggle(second.promise) // true -> false

    expect(state.flag).toBe(false)

    first.reject()
    second.resolve()
    await expect(firstResult).resolves.toBe(false)
    await expect(secondResult).resolves.toBe(true)

    // The second toggle's outcome stands; the first's rollback stayed away.
    expect(state.flag).toBe(false)
  })

  it('still rolls back the newest of several failed mutations', async () => {
    const state = { count: 0 }
    const key = 'media/1/count'
    const first = deferred()
    const second = deferred()

    const firstResult = optimistic(
      () => {
        state.count = 1

        return () => { state.count = 0 }
      },
      () => first.promise,
      key,
    )
    const secondResult = optimistic(
      () => {
        const previous = state.count
        state.count = 2

        return () => { state.count = previous }
      },
      () => second.promise,
      key,
    )

    first.reject()
    second.reject()
    await expect(firstResult).resolves.toBe(false)
    await expect(secondResult).resolves.toBe(false)

    // The newest failure rolls back to what it saw when it applied — the
    // first mutation's optimistic value; the first's own rollback is stale.
    expect(state.count).toBe(1)
  })

  it('keeps the pre-key behaviour when no key is given', async () => {
    const state = { flag: false }
    const request = deferred()

    const result = optimistic(
      () => {
        state.flag = true

        return () => { state.flag = false }
      },
      () => request.promise,
    )

    request.reject()
    await expect(result).resolves.toBe(false)
    expect(state.flag).toBe(false)
  })
})
