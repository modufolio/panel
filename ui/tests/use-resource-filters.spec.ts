import { describe, it, expect, vi, beforeEach } from 'vitest'

// Hoisted with the mock: the composable's module graph loads before any plain
// `const` here would be initialised.
const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@inertiajs/vue3', () => ({ router: { get, visit: vi.fn(), prefetch: vi.fn(), flushAll: vi.fn() } }))

const { useResourceFilters } = await import('../src/Composables/useResourceFilters')
type TableSchema = import('../src/Components/Table/tableSchema').TableSchema

/**
 * The filter wiring a hand-written index page gets. A generated page goes
 * through `useResourceListing`, which wraps this same call together with the
 * rows and the drawer stack.
 */
const table = {
  columns: [{ key: 'name', label: 'Name', type: 'text' }],
  filters: [
    { key: 'role', label: 'Role', type: 'select', options: [] },
    { key: 'team', label: 'Team', type: 'select', options: [] },
  ],
} as unknown as TableSchema

beforeEach(() => {
  get.mockClear()
})

describe('useResourceFilters', () => {
  it('seeds the form from the given filters over the schema\'s own defaults', () => {
    const { form } = useResourceFilters('/users', { search: 'ada', role: 'admin' }, table)

    expect(form.search).toBe('ada')
    expect(form.role).toBe('admin')
    // Declared by the schema, not sent by the server: present and empty, so
    // the control renders rather than appearing only once it has a value.
    expect(form.team).toBe('')
  })

  it('writes one filter without the caller redeclaring the setter', () => {
    const { form, setFilter } = useResourceFilters('/users', { search: 'ada' }, table)

    setFilter('role', 'admin')

    expect(form.role).toBe('admin')
  })

  it('threads the bound page size through, so callers pass only the page', () => {
    const { goToPage } = useResourceFilters('/users', {}, table, () => 25)

    goToPage(3)

    expect(get.mock.calls[0][1]).toMatchObject({ page: { number: 3, size: 25 } })
  })

  it('sends no size when nothing bound one', () => {
    const { goToPage } = useResourceFilters('/users', {}, table)

    goToPage(2)

    expect(get.mock.calls[0][1]).toMatchObject({ page: { number: 2 } })
    expect(get.mock.calls[0][1].page).not.toHaveProperty('size')
  })

  it('an explicit size still wins over the bound one', () => {
    const { goToPage } = useResourceFilters('/users', {}, table, () => 25)

    goToPage(2, 50)

    expect(get.mock.calls[0][1]).toMatchObject({ page: { number: 2, size: 50 } })
  })
})
