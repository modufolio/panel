import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { BooleanColumn } from '../src/index'

/**
 * The default icons are render functions, not template strings: a host on the
 * runtime-only Vue build cannot compile a template at runtime, and the cell
 * rendered a coloured disc with nothing inside it.
 */
describe('BooleanColumn icons', () => {
  it('draws a check for true and a cross for false, as real SVG', () => {
    const yes = mount(BooleanColumn, { props: { value: true } })
    const no = mount(BooleanColumn, { props: { value: false } })

    expect(yes.find('svg path').attributes('d')).toBe('M4.5 12.75l6 6 9-13.5')
    expect(no.find('svg path').attributes('d')).toBe('M6 18L18 6M6 6l12 12')
  })

  it('colours the false state as asked', () => {
    const no = mount(BooleanColumn, { props: { value: false, falseColor: 'danger' } })

    expect(no.find('span').classes()).toContain('text-danger-600')
  })
})
