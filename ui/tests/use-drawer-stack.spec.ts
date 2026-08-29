import { describe, it, expect, vi, beforeEach } from 'vitest'

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit } }))

const { useDrawerStack } = await import('../src/Components/Drawer/useDrawerStack')

describe('useDrawerStack.pushWithParams', () => {
  beforeEach(() => {
    visit.mockClear()
  })

  it('pushes the base URL and id with no query string when params are empty', () => {
    const drawerStack = useDrawerStack({ stack: [] }, '/panel/contacts')

    drawerStack.pushWithParams(42)

    expect(visit).toHaveBeenCalledWith('/panel/contacts/42', expect.anything())
  })

  it('appends a query string built from the params', () => {
    const drawerStack = useDrawerStack({ stack: [] }, '/panel/organizations')

    drawerStack.pushWithParams(7, { status: 'lead', sort: 'name' })

    expect(visit).toHaveBeenCalledWith(
      '/panel/organizations/7?status=lead&sort=name',
      expect.anything(),
    )
  })

  it('accepts a string id', () => {
    const drawerStack = useDrawerStack({ stack: [] }, '/panel/contacts')

    drawerStack.pushWithParams('uuid-123', { q: 'ada' })

    expect(visit).toHaveBeenCalledWith('/panel/contacts/uuid-123?q=ada', expect.anything())
  })
})
