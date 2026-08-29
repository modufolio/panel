import { describe, it, expect } from 'vitest'
import { ref, nextTick, effectScope, reactive } from 'vue'
import { useReconciledReactive } from '../src/index'

interface Media {
  id: number
  title: string
  tags: { id: number; name: string }[]
}

describe('useReconciledReactive', () => {
  it('creates a mutable copy and resyncs from the source in place', async () => {
    const source = ref<Media>({ id: 1, title: 'a', tags: [{ id: 1, name: 'x' }] })
    let state!: Media
    const scope = effectScope()
    scope.run(() => {
      state = useReconciledReactive(() => source.value)
    })

    expect(state.title).toBe('a')

    // A local optimistic edit is allowed
    state.title = 'local'

    const tagsRef = state.tags // capture identity

    // Server re-sends the prop
    source.value = { id: 1, title: 'server', tags: [{ id: 1, name: 'y' }, { id: 2, name: 'z' }] }
    await nextTick()

    expect(state.title).toBe('server') // reconciled from server
    expect(state.tags).toBe(tagsRef) // array identity preserved (not rebuilt)
    expect(state.tags).toHaveLength(2)
    expect(state.tags[0].name).toBe('y') // matched row updated in place

    scope.stop()
  })

  it('is a plain reactive object usable like reactive({ ...props })', () => {
    const source = reactive({ id: 1, n: 0 })
    const scope = effectScope()
    let state!: { id: number; n: number }
    scope.run(() => {
      state = useReconciledReactive(() => source)
    })
    expect(state.n).toBe(0)
    state.n = 5
    expect(state.n).toBe(5)
    scope.stop()
  })
})
