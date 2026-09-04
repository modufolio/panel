import { describe, it, expect, vi, beforeEach } from 'vitest'

const patch = vi.fn()
const post = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { patch, post } }))

const { useInlineEdit } = await import('../src/Components/Composables/useInlineEdit')

/**
 * A partial reload returns ONLY the props named in `only`, and the server
 * filters shared props the same way. An inline edit is precisely where the
 * server answers with a flash — a refused role change, a permission error —
 * so omitting `flash` from `only` makes the refusal invisible: Inertia keeps
 * the stale value, AppLayout's watcher never fires, and the row snaps back
 * with no explanation. That is a silent failure, which is why it is pinned.
 */
describe('useInlineEdit partial reloads', () => {
  beforeEach(() => {
    patch.mockClear()
    post.mockClear()
  })

  const record = { id: 7 }

  it('adds the shared props the layout needs to a caller-supplied only list', () => {
    const { updateField } = useInlineEdit({ endpoint: '/users', only: ['users'] })

    void updateField(record, 'role', 'super_admin')

    const [, , options] = patch.mock.calls[0]

    expect(options.only).toContain('users')
    expect(options.only).toContain('flash')
    expect(options.only).toContain('errors')
  })

  it('keeps the caller\'s own props', () => {
    const { updateField } = useInlineEdit({ endpoint: '/users', only: ['users', 'filters'] })

    void updateField(record, 'role', 'admin')

    const [, , options] = patch.mock.calls[0]

    expect(options.only).toEqual(expect.arrayContaining(['users', 'filters', 'flash', 'errors']))
  })

  it('does not duplicate a prop the caller already asked for', () => {
    const { updateField } = useInlineEdit({ endpoint: '/users', only: ['users', 'flash'] })

    void updateField(record, 'role', 'admin')

    const [, , options] = patch.mock.calls[0]

    expect(options.only.filter((p: string) => p === 'flash')).toHaveLength(1)
  })

  it('leaves a full reload alone — no only key is sent', () => {
    const { updateField } = useInlineEdit({ endpoint: '/users' })

    void updateField(record, 'role', 'admin')

    const [, , options] = patch.mock.calls[0]

    expect(options).not.toHaveProperty('only')
  })

  it('applies to updateRecord as well as updateField', () => {
    const { updateRecord } = useInlineEdit({ endpoint: '/users', only: ['users'] })

    void updateRecord(record, { role: 'admin', account_status: 'active' })

    const [, , options] = patch.mock.calls[0]

    expect(options.only).toEqual(expect.arrayContaining(['users', 'flash', 'errors']))
  })
})
