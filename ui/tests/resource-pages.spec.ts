import { describe, it, expect, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  Head: { name: 'Head', props: ['title'], template: '<div />' },
  router: { visit: vi.fn(), prefetch: vi.fn(), flushAll: vi.fn(), on: vi.fn() },
  useForm: vi.fn(),
  usePage: () => ({ props: {} }),
  Link: { name: 'Link', template: '<a><slot /></a>' },
}))

const { resourcePages } = await import('../src/Pages/resourcePages')

/**
 * The map an app spreads into its Inertia resolver. Every generated route the
 * server can render must have an entry here, and every entry must load: a
 * missing or broken one is a page the backend renders into nothing.
 */
describe('resourcePages', () => {
  it('covers the four generated page names', () => {
    expect(Object.keys(resourcePages).sort()).toEqual([
      'Resource/Create',
      'Resource/Edit',
      'Resource/Index',
      'Resource/Permissions',
    ])
  })

  // Generous: each of these pulls in the whole listing or form component
  // graph behind it, which is slow to transform on a cold run.
  it.each(Object.entries(resourcePages))('%s resolves to a component', async (_name, load) => {
    const module = await load()
    expect(module.default).toBeTruthy()
  }, 30_000)

  it('declares no layout, leaving that to the app resolver', async () => {
    for (const load of Object.values(resourcePages)) {
      const component = (await load()).default as Record<string, unknown>
      expect(component.layout).toBeUndefined()
    }
  })
})
