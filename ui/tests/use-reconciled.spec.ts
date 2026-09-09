import { describe, it, expect } from 'vitest'
import { ref, nextTick } from 'vue'
import { useReconciled, useReconciledReactive } from '../src/Composables/useReconciled'

interface Row { id: number; title: string }
interface Payload { title: string; rows: Row[] }

function payload(): Payload {
  return { title: 'Casablanca', rows: [{ id: 1, title: 'Rick' }, { id: 2, title: 'Ilsa' }] }
}

describe('useReconciled', () => {
  it('starts as a copy the caller may edit without touching the source', async () => {
    const source = ref(payload())
    const local = useReconciled(() => source.value)

    local.value.title = 'Locally renamed'
    await nextTick()

    expect(source.value.title).toBe('Casablanca')
  })

  it('keeps row identity when the source is re-sent unchanged', async () => {
    const source = ref(payload())
    const local = useReconciled(() => source.value)
    const firstRow = local.value.rows[0]

    // Inertia re-sends the prop: a new object graph carrying the same data.
    source.value = payload()
    await nextTick()

    expect(local.value.rows[0]).toBe(firstRow)
  })

  it('updates the leaves that changed, and only those', async () => {
    const source = ref(payload())
    const local = useReconciled(() => source.value)
    const untouched = local.value.rows[1]

    const next = payload()
    next.rows[0].title = 'Richard'
    source.value = next
    await nextTick()

    expect(local.value.rows[0].title).toBe('Richard')
    expect(local.value.rows[1]).toBe(untouched)
  })

  it('drops rows the server no longer sends', async () => {
    const source = ref(payload())
    const local = useReconciled(() => source.value)

    source.value = { title: 'Casablanca', rows: [{ id: 2, title: 'Ilsa' }] }
    await nextTick()

    expect(local.value.rows.map((row) => row.id)).toEqual([2])
  })

  it('overwrites a local edit when the server sends that field again', async () => {
    const source = ref(payload())
    const local = useReconciled(() => source.value)

    local.value.title = 'Locally renamed'
    source.value = payload()
    await nextTick()

    // The server is the authority on what it sent; a local edit to a field the
    // payload carries is a draft, not a source of truth.
    expect(local.value.title).toBe('Casablanca')
  })
})

describe('useReconciledReactive', () => {
  it('is the same thing without the .value', async () => {
    const source = ref(payload())
    const local = useReconciledReactive(() => source.value)
    const firstRow = local.rows[0]

    expect(local.title).toBe('Casablanca')

    source.value = payload()
    await nextTick()

    expect(local.rows[0]).toBe(firstRow)
  })
})
