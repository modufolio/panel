import { describe, expect, it } from 'vitest'
import { effectScope } from 'vue'
import { useFieldSaver } from '../src/Composables/useFieldSaver'

function deferred(): { promise: Promise<void>; resolve: () => void; reject: () => void } {
  let resolve!: () => void
  let reject!: () => void
  const promise = new Promise<void>((res, rej) => {
    resolve = res
    reject = () => rej(new Error('save failed'))
  })

  return { promise, resolve, reject }
}

/** Composables using onScopeDispose need a scope to register against. */
function inScope<T>(setup: () => T): T {
  const scope = effectScope()
  const result = scope.run(setup)

  return result as T
}

describe('useFieldSaver', () => {
  it('reports saving then saved for a single save', async () => {
    const request = deferred()
    const saver = inScope(() => useFieldSaver(() => request.promise))

    const pending = saver.save()

    expect(saver.saveStatus.value).toBe('saving')
    request.resolve()
    await pending
    expect(saver.saveStatus.value).toBe('saved')
  })

  it('a stale save settling late cannot clobber the newer save\'s outcome', async () => {
    const first = deferred()
    const second = deferred()
    const requests = [first, second]
    const saver = inScope(() => useFieldSaver(() => requests.shift()!.promise))

    const firstPending = saver.save()
    const secondPending = saver.save()

    // The network reorders: the newer save settles first, then the stale one.
    second.resolve()
    await secondPending
    expect(saver.saveStatus.value).toBe('saved')

    first.reject()
    await expect(firstPending).rejects.toThrow('save failed')
    // The stale failure must not repaint the badge red: the field's current
    // value was saved by the newer request, and that is what the user sees.
    expect(saver.saveStatus.value).toBe('saved')
  })

  it('a stale success cannot hide the newer save\'s error', async () => {
    const first = deferred()
    const second = deferred()
    const requests = [first, second]
    const saver = inScope(() => useFieldSaver(() => requests.shift()!.promise))

    const firstPending = saver.save()
    const secondPending = saver.save()

    second.reject()
    await expect(secondPending).rejects.toThrow('save failed')
    expect(saver.saveStatus.value).toBe('error')

    first.resolve()
    await firstPending
    // The newest save failed; a stale "saved" would tell the user their
    // latest value is on the server when it is not.
    expect(saver.saveStatus.value).toBe('error')
  })
})
