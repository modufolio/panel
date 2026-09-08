import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DrawerFieldGrid from '../src/Components/Drawer/DrawerFieldGrid.vue'

/**
 * ISO-8601 is a transport format: `2026-09-08T07:26:29+00:00` in a drawer is
 * a machine talking. A hex literal is the same problem in the other
 * direction — `#6366f1` is a colour, and a reader should see the colour.
 */
describe('DrawerFieldGrid value formatting', () => {
  const textOf = (data: Record<string, unknown>) =>
    mount(DrawerFieldGrid, { props: { data } }).text()

  it('reads a timestamp as a date and a time', () => {
    const text = textOf({ created_at: '2026-09-08T07:26:29+00:00' })

    expect(text).toContain('Sep 8, 2026')
    expect(text).not.toContain('2026-09-08T07:26:29')
  })

  /** Local midnight, not UTC: `new Date('2026-09-08')` is the day before west of Greenwich. */
  it('reads a plain date without inventing a time', () => {
    const text = textOf({ released_on: '2026-09-08' })

    expect(text).toContain('Sep 8, 2026')
    expect(text).not.toContain('00:00')
  })

  it('leaves text that merely starts with digits alone', () => {
    expect(textOf({ reference: '2026-Q3 budget' })).toContain('2026-Q3 budget')
    expect(textOf({ phone: '0648724697' })).toContain('0648724697')
  })

  it('shows a hex colour as a swatch, keeping the literal', () => {
    const wrapper = mount(DrawerFieldGrid, { props: { data: { color: '#6366f1' } } })
    const swatch = wrapper.find('[aria-hidden="true"]')

    expect(swatch.attributes('style')).toContain('background-color: #6366f1')
    expect(wrapper.text()).toContain('#6366f1')
  })

  it('leaves a string that is not a colour as text', () => {
    const wrapper = mount(DrawerFieldGrid, { props: { data: { note: '#6366f1x' } } })

    expect(wrapper.find('span[style]').exists()).toBe(false)
    expect(wrapper.text()).toContain('#6366f1x')
  })
})
