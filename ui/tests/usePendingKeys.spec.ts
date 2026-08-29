import { describe, expect, it } from 'vitest'
import { usePendingKeys } from '../src/Composables/usePendingKeys'

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason?: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })

  return { promise, resolve, reject }
}

describe('usePendingKeys', () => {
  it('tracks per key, so one row saving does not mark the others', async () => {
    const { isPending, run } = usePendingKeys()
    const request = deferred<string>()

    const pending = run(1, () => request.promise)

    expect(isPending(1)).toBe(true)
    expect(isPending(2)).toBe(false)

    request.resolve('done')
    expect(await pending).toBe('done')
    expect(isPending(1)).toBe(false)
  })

  it('refuses re-entry for a key already in flight — the double-click guard', async () => {
    const { run } = usePendingKeys()
    const request = deferred<string>()
    let calls = 0

    const first = run('a', () => {
      calls++

      return request.promise
    })
    const second = await run('a', () => {
      calls++

      return Promise.resolve('nope')
    })

    expect(second).toBeUndefined()
    expect(calls).toBe(1)

    request.resolve('yes')
    expect(await first).toBe('yes')
  })

  it('releases the key when the request rejects, and propagates the error', async () => {
    const { isPending, run } = usePendingKeys()
    const request = deferred<string>()

    const pending = run('a', () => request.promise)
    request.reject(new Error('boom'))

    await expect(pending).rejects.toThrow('boom')
    expect(isPending('a')).toBe(false)
  })

  it('anyPending reflects the whole set', async () => {
    const { anyPending, run } = usePendingKeys()
    const first = deferred<void>()
    const second = deferred<void>()

    const a = run(1, () => first.promise)
    const b = run(2, () => second.promise)

    expect(anyPending.value).toBe(true)

    first.resolve()
    await a
    expect(anyPending.value).toBe(true)

    second.resolve()
    await b
    expect(anyPending.value).toBe(false)
  })
})
