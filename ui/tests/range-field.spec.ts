import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { RangeField } from '../src/index'

const mountField = (props: Record<string, unknown> = {}) =>
  mount(RangeField, {
    props: { modelValue: 220, label: 'Sidebar width', min: 180, max: 400, step: 10, ...props },
  })

describe('RangeField', () => {
  it('renders a range input with its bounds', () => {
    const input = mountField().find('input[type="range"]')

    expect(input.exists()).toBe(true)
    expect(input.attributes('min')).toBe('180')
    expect(input.attributes('max')).toBe('400')
    expect(input.attributes('step')).toBe('10')
  })

  it('shows the current value, since a slider has no visible value of its own', () => {
    const wrapper = mountField({ suffix: 'px' })

    expect(wrapper.find('.ui-field-range-value').text()).toBe('220 px')
  })

  it('emits a number, not the input string', async () => {
    const wrapper = mountField()
    const input = wrapper.find('input[type="range"]')

    await input.setValue('300')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    expect(emitted![0]).toEqual([300])
    expect(typeof emitted![0][0]).toBe('number')
  })

  it('announces the unit to screen readers', () => {
    const input = mountField({ suffix: 'px' }).find('input[type="range"]')

    expect(input.attributes('aria-valuetext')).toBe('220 px')
  })

  it('wires help and error through aria-describedby', () => {
    const wrapper = mountField({ id: 'w', help: 'Pixels.', error: 'Too wide' })
    const input = wrapper.find('input[type="range"]')

    expect(input.attributes('aria-describedby')).toBe('w-help w-error')
    expect(wrapper.find('.ui-field-error').attributes('role')).toBe('alert')
  })

  it('can be disabled', () => {
    const input = mountField({ disabled: true }).find('input[type="range"]')

    expect(input.attributes('disabled')).toBeDefined()
  })
})
