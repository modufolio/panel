import { describe, it, expect } from 'vitest'
import { optimistic } from '../src/index'

describe('optimistic', () => {
  it('applies the change and keeps it on success', async () => {
    const state = { fav: false }
    const ok = await optimistic(
      () => {
        state.fav = true
        return () => { state.fav = false }
      },
      async () => 'done',
    )
    expect(ok).toBe(true)
    expect(state.fav).toBe(true)
  })

  it('rolls back on failure and returns false', async () => {
    const state = { fav: false }
    const ok = await optimistic(
      () => {
        state.fav = true
        return () => { state.fav = false }
      },
      async () => { throw new Error('nope') },
    )
    expect(ok).toBe(false)
    expect(state.fav).toBe(false)
  })

  it('applies before the request resolves', async () => {
    const state = { n: 0 }
    let resolve!: () => void
    const p = optimistic(
      () => { state.n = 1; return () => { state.n = 0 } },
      () => new Promise<void>((r) => { resolve = r }),
    )
    expect(state.n).toBe(1) // already applied while request pending
    resolve()
    await p
    expect(state.n).toBe(1)
  })
})

describe('useAsyncData mutate', () => {
  it('optimistically sets data via value or updater', async () => {
    const { useAsyncData } = await import('@/Composables/useAsyncData')
    const { data, mutate } = useAsyncData(async () => ({ n: 1 }), { initialData: { n: 0 } })
    mutate({ n: 5 })
    expect(data.value).toEqual({ n: 5 })
    mutate((c) => ({ n: (c?.n ?? 0) + 1 }))
    expect(data.value).toEqual({ n: 6 })
  })
})
