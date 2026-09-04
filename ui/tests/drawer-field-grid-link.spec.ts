import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DrawerFieldGrid from '../src/Components/Drawer/DrawerFieldGrid.vue'

/**
 * A reference the presenter gave an `href` is another record worth opening.
 * The table column pointing at the same record was already a link; the drawer
 * showed dead text, so you could reach it from the list but not from the
 * record you had open.
 */
describe('DrawerFieldGrid reference links', () => {
  const grid = (data: Record<string, unknown>) => mount(DrawerFieldGrid, { props: { data } })

  it('renders a reference with an href as a link', () => {
    const wrapper = grid({ organization: { id: 'x', name: 'Cave7', href: '/panel/contacts/a/organization/b' } })
    const link = wrapper.find('a')

    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/panel/contacts/a/organization/b')
    expect(link.text()).toContain('Cave7')
  })

  it('leaves a reference without an href as text', () => {
    const wrapper = grid({ organization: { id: 'x', name: 'Cave7' } })

    expect(wrapper.find('a').exists()).toBe(false)
    expect(wrapper.text()).toContain('Cave7')
  })

  /**
   * `url` is the media shape. A link keyed `url` would render as a broken
   * image, which is why the link key is `href`.
   */
  it('still treats an object with url as media, not a link', () => {
    const wrapper = grid({ cover: { name: 'x', url: '/img/x.jpg' } })

    expect(wrapper.find('img').exists()).toBe(true)
    expect(wrapper.find('a').exists()).toBe(false)
  })

  it('leaves scalars alone', () => {
    expect(grid({ phone: '0648724697' }).find('a').exists()).toBe(false)
  })
})
