import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@inertiajs/vue3', () => ({
  Head: { name: 'Head', template: '<div />' },
  Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { visit: vi.fn() },
}))

const { default: ResourceEditPage } = await import('../src/Pages/ResourceEditPage.vue')
const { default: RecordNavigation } = await import('../src/Components/Layout/RecordNavigation.vue')

const mounted: Array<{ unmount: () => void }> = []

const mountPage = (recordNavigation?: { next: string | null, previous: string | null }) => {
  const wrapper = mount(ResourceEditPage, {
    props: {
      resource: { key: 'movies', label: 'Movie', baseUrl: '/panel/movies', urls: {}, drawerType: 'movie' },
      fields: [],
      record: { title: 'Jaws' },
      ...(recordNavigation ? { recordNavigation } : {}),
    },
    attachTo: document.body,
    global: { stubs: { ResourceForm: true } },
  })

  mounted.push(wrapper)

  return wrapper
}

/**
 * What the edit page itself owns: handing the neighbour URLs to the shared
 * pair, and leaving the header bare when there are none. How the pair behaves
 * once it has them is RecordNavigation's own spec.
 */
describe('Resource edit record navigation', () => {
  beforeEach(() => { while (mounted.length > 0) mounted.pop()?.unmount() })
  afterEach(() => {
    while (mounted.length > 0) mounted.pop()?.unmount()
    document.body.innerHTML = ''
  })

  it('hands the neighbour URLs to the shared navigation, named after the resource', () => {
    const wrapper = mountPage({ next: '/panel/movies/jaws/edit', previous: '/panel/movies/heat/edit' })
    const nav = wrapper.getComponent(RecordNavigation)

    expect(nav.props('previous')).toBe('/panel/movies/heat/edit')
    expect(nav.props('next')).toBe('/panel/movies/jaws/edit')
    // "Previous movie", not "Previous Movie" or "Previous record".
    expect(nav.props('label')).toBe('movie')
  })

  it('still renders the pair when only one side has a neighbour', () => {
    const wrapper = mountPage({ next: '/panel/movies/jaws/edit', previous: null })

    expect(wrapper.findComponent(RecordNavigation).exists()).toBe(true)
    expect(wrapper.get('[data-testid="record-nav-previous"]').attributes('aria-disabled')).toBe('true')
  })

  it('draws no navigation at all for a page that was given none', () => {
    const wrapper = mountPage()

    expect(wrapper.findComponent(RecordNavigation).exists()).toBe(false)
    expect(wrapper.find('[data-testid="record-nav-next"]').exists()).toBe(false)
  })
})
