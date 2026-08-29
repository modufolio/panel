import { describe, it, expect, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { useAsyncData } from '../src/index'

describe('useAsyncData', () => {
  it('tracks loading and resolves data', async () => {
    const { data, loading, execute } = useAsyncData(async (_ctx, n: number) => n * 2)
    expect(loading.value).toBe(false)
    const p = execute(21)
    expect(loading.value).toBe(true)
    const result = await p
    expect(result).toBe(42)
    expect(data.value).toBe(42)
    expect(loading.value).toBe(false)
  })

  it('captures errors on error rather than throwing, and returns null', async () => {
    const onError = vi.fn()
    const { data, error, execute } = useAsyncData(async () => {
      throw new Error('boom')
    }, { onError })
    const result = await execute()
    expect(result).toBeNull()
    expect(data.value).toBeNull()
    expect((error.value as Error).message).toBe('boom')
    expect(onError).toHaveBeenCalledOnce()
  })

  it('discards a stale response when a newer call supersedes it', async () => {
    const resolvers: Array<(v?: string) => void> = []
    const { data, execute } = useAsyncData(
      (_ctx, label: string) => new Promise<string>((res) => resolvers.push(() => res(label))),
    )
    const first = execute('old')
    const second = execute('new')
    // Resolve the newer request first, then the stale one.
    resolvers[1]()
    resolvers[0]()
    await Promise.all([first, second])
    expect(data.value).toBe('new')
  })

  it('aborts the superseded request via the fetch context signal', async () => {
    const signals: AbortSignal[] = []
    const { execute } = useAsyncData(
      ({ signal }) => {
        signals.push(signal)
        return new Promise<string>(() => {})
      },
    )
    void execute()
    void execute()
    expect(signals[0].aborted).toBe(true)
    expect(signals[1].aborted).toBe(false)
  })

  it('aborts the in-flight request when the scope is disposed', () => {
    const signals: AbortSignal[] = []
    const scope = effectScope()
    scope.run(() => {
      const { execute } = useAsyncData(({ signal }) => {
        signals.push(signal)
        return new Promise<string>(() => {})
      })
      void execute()
    })
    scope.stop()
    expect(signals[0].aborted).toBe(true)
  })

  it('treats an AbortError as silence, not an error', async () => {
    const onError = vi.fn()
    const { error, execute } = useAsyncData(
      async () => {
        throw new DOMException('The operation was aborted.', 'AbortError')
      },
      { onError },
    )
    await execute()
    expect(error.value).toBeNull()
    expect(onError).not.toHaveBeenCalled()
  })

  it('ignores a response that resolves after the scope is disposed', async () => {
    let release!: (v: number) => void
    const scope = effectScope()
    let handle!: ReturnType<typeof useAsyncData<number, []>>
    scope.run(() => {
      handle = useAsyncData(() => new Promise<number>((res) => { release = res }))
    })
    const p = handle.execute()
    scope.stop()
    release(99)
    await p
    expect(handle.data.value).toBeNull()
    expect(handle.loading.value).toBe(true) // finally is skipped post-dispose; state is frozen
  })

  it('refreshes with the arguments from the last execute', async () => {
    const fetcher = vi.fn(async (_ctx, n: number) => n)
    const { execute, refresh } = useAsyncData(fetcher)
    await execute(7)
    await refresh()
    expect(fetcher).toHaveBeenLastCalledWith(expect.objectContaining({ signal: expect.any(AbortSignal) }), 7)
  })

  it('refetches when a watched source changes', async () => {
    const src = ref(0)
    const fetcher = vi.fn(async () => 'x')
    useAsyncData(fetcher, { immediate: true, watch: src })
    await nextTick()
    expect(fetcher).toHaveBeenCalledTimes(1)
    src.value = 1
    await nextTick()
    expect(fetcher).toHaveBeenCalledTimes(2)
  })
})
