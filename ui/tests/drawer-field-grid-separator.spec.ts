import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DrawerFieldGrid from '../src/Components/Drawer/DrawerFieldGrid.vue'

/** A separator in the include map is a break across the row, not a field with a label. */
describe('DrawerFieldGrid separators', () => {
  const data = { first_name: 'Leila', email: 'a@b.c', note: 'n' }
  const include = {
    first_name: 'First name',
    separator_1: { separator: 'line' as const },
    email: null,
    separator_2: { separator: 'space' as const },
    note: { label: 'Note', wide: true },
  }

  it('renders a break where the map declares one, spanning the row', () => {
    const wrapper = mount(DrawerFieldGrid, { props: { data, include } })
    const breaks = wrapper.findAll('.ui-drawer-field-separator')

    expect(breaks).toHaveLength(2)
    expect(breaks[0].classes()).toContain('border-t')
    expect(breaks[1].classes()).not.toContain('border-t')
    expect(breaks[0].element.parentElement?.classList.contains('col-span-2')).toBe(true)
  })

  it('lets the map claim the full row for a field, as the form did', () => {
    const wrapper = mount(DrawerFieldGrid, { props: { data, include } })
    const note = wrapper.findAll('dt').find((dt) => dt.text() === 'Note')?.element.parentElement

    expect(note?.classList.contains('col-span-2')).toBe(true)
  })

  it('gives a break no label and keeps the fields around it', () => {
    const wrapper = mount(DrawerFieldGrid, { props: { data, include } })

    expect(wrapper.findAll('dt').map((dt) => dt.text())).toEqual(['First name', 'Email', 'Note'])
  })
})
