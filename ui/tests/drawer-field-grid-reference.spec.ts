import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DrawerFieldGrid from '../src/Components/Drawer/DrawerFieldGrid.vue'

/**
 * A presenter that emits a whole related record rather than a scalar used to
 * reach String(value) and render as "[object Object]". The grid knew about
 * media references and scalars; a relation reference is neither.
 */
describe('DrawerFieldGrid relation references', () => {
  const textOf = (data: Record<string, unknown>) =>
    mount(DrawerFieldGrid, { props: { data } }).text()

  it('renders a reference by its name', () => {
    const text = textOf({ organization: { id: 'abc', name: 'Cave7' } })

    expect(text).toContain('Cave7')
    expect(text).not.toContain('[object Object]')
  })

  it('falls back through title and label', () => {
    expect(textOf({ thing: { id: '1', title: 'Arrival' } })).toContain('Arrival')
    expect(textOf({ thing: { id: '1', label: 'Office' } })).toContain('Office')
  })

  it('shows an em dash for an object with no readable label', () => {
    const text = textOf({ thing: { id: '1', count: 3 } })

    expect(text).not.toContain('[object Object]')
    expect(text).toContain('—')
  })

  it('leaves scalars alone', () => {
    expect(textOf({ phone: '0648724697' })).toContain('0648724697')
  })

  it('still prefers a media reference over a label', () => {
    const wrapper = mount(DrawerFieldGrid, {
      props: { data: { cover: { name: 'ignored', thumbnail_url: '/img/x.jpg' } } },
    })

    expect(wrapper.find('img').attributes('src')).toBe('/img/x.jpg')
  })
})
