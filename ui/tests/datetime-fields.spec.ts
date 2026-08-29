import { describe, it, expect } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { DateTimePickerField, TimePickerField, NestedDrawerForm } from '../src/index'

/** The last `update:modelValue` payload. Avoids `Array.prototype.at`, which
 *  this project's TS lib target does not carry. */
function lastEmit(wrapper: VueWrapper<any>): unknown[] | undefined {
  const emitted = wrapper.emitted('update:modelValue')
  return emitted?.[emitted.length - 1]
}

describe('TimePickerField', () => {
  it('renders a native time input carrying the value', () => {
    const wrapper = mount(TimePickerField, { props: { modelValue: '14:30', label: 'Starts' } })
    const input = wrapper.find('input')

    expect(input.attributes('type')).toBe('time')
    expect((input.element as HTMLInputElement).value).toBe('14:30')
  })

  it('emits what the control reports, including the empty half-typed state', async () => {
    const wrapper = mount(TimePickerField, { props: { modelValue: '' } })
    const input = wrapper.find('input')

    await input.setValue('09:15')
    expect(lastEmit(wrapper)).toEqual(['09:15'])

    await input.setValue('')
    expect(lastEmit(wrapper)).toEqual([''])
  })

  it('wires help and error text to the input for screen readers', () => {
    const wrapper = mount(TimePickerField, {
      props: { id: 'f', modelValue: '', help: 'Local time', error: 'Required' },
    })

    expect(wrapper.find('input').attributes('aria-describedby')).toBe('f-help f-error')
    expect(wrapper.find('input').attributes('aria-invalid')).toBe('true')
  })
})

describe('DateTimePickerField', () => {
  const mountField = (modelValue: string) =>
    mount(DateTimePickerField, { props: { modelValue, label: 'Starts at' } })

  it('splits a stored value across the date and time halves', () => {
    const wrapper = mountField('2026-09-01T14:30')

    expect((wrapper.find('input[type="time"]').element as HTMLInputElement).value).toBe('14:30')
    expect(wrapper.findComponent({ name: 'DatePickerField' }).props('modelValue')).toBe('2026-09-01')
  })

  it.each([
    ['2026-09-01 14:30', '14:30'],
    ['2026-09-01T14:30:00', '14:30'],
    ['2026-09-01T14:30:00Z', '14:30'],
    ['2026-09-01', ''],
  ])('tolerates %s as stored form', (stored, time) => {
    expect((mountField(stored).find('input[type="time"]').element as HTMLInputElement).value).toBe(time)
  })

  it('fills in a default time when only a date has been chosen', async () => {
    const wrapper = mountField('')

    wrapper.findComponent({ name: 'DatePickerField' }).vm.$emit('update:modelValue', '2026-09-01')
    await wrapper.vm.$nextTick()

    expect(lastEmit(wrapper)).toEqual(['2026-09-01T09:00'])
  })

  it('keeps the date when the time changes', async () => {
    const wrapper = mountField('2026-09-01T09:00')

    await wrapper.find('input[type="time"]').setValue('16:45')

    expect(lastEmit(wrapper)).toEqual(['2026-09-01T16:45'])
  })

  it('clears to empty when the date is cleared — a time alone is not a moment', async () => {
    const wrapper = mountField('2026-09-01T09:00')

    wrapper.findComponent({ name: 'DatePickerField' }).vm.$emit('update:modelValue', '')
    await wrapper.vm.$nextTick()

    expect(lastEmit(wrapper)).toEqual([''])
  })
})

describe('NestedDrawerForm date and checkbox fields', () => {
  it('renders the native control for each new type', () => {
    const wrapper = mount(NestedDrawerForm, {
      props: {
        fields: [
          { name: 'starts_at', label: 'Starts', type: 'datetime' },
          { name: 'ends_at', label: 'Ends', type: 'time' },
          { name: 'day', label: 'Day', type: 'date' },
          { name: 'all_day', label: 'All day', type: 'checkbox' },
        ],
        state: { starts_at: '2026-09-01T09:00', ends_at: '10:00', day: '2026-09-01', all_day: true },
        errors: {},
      } as any,
    })

    expect(wrapper.find('#starts_at').attributes('type')).toBe('datetime-local')
    expect(wrapper.find('#ends_at').attributes('type')).toBe('time')
    expect(wrapper.find('#day').attributes('type')).toBe('date')
    expect((wrapper.find('#all_day').element as HTMLInputElement).checked).toBe(true)
  })

  it('reports a checkbox as a boolean, not a string', async () => {
    const wrapper = mount(NestedDrawerForm, {
      props: {
        fields: [{ name: 'all_day', label: 'All day', type: 'checkbox' }],
        state: { all_day: false },
        errors: {},
      } as any,
    })

    await wrapper.find('#all_day').setValue(true)

    const emitted = wrapper.emitted('update:field')
    expect(emitted?.[emitted.length - 1]).toEqual(['all_day', true])
  })
})
