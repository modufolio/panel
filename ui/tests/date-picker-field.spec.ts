import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { DatePickerField } from '../src/index'

const mountField = (props: Record<string, unknown> = {}) =>
  mount(DatePickerField, {
    props: { modelValue: '', label: 'Publish date', ...props },
    attachTo: document.body,
  })

describe('DatePickerField', () => {
  it('shows the committed value as dd/mm/yyyy', () => {
    const wrapper = mountField({ modelValue: '2026-08-22' })
    const input = wrapper.find('input').element as HTMLInputElement
    expect(input.value).toBe('22/08/2026')
  })

  it('opens the calendar on the toggle button', async () => {
    const wrapper = mountField()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

    await wrapper.find('button[aria-label="Toggle calendar"]').trigger('click')

    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    expect(wrapper.find('table[role="grid"]').exists()).toBe(true)
  })

  it('commits on Enter and emits ISO', async () => {
    const wrapper = mountField()
    const input = wrapper.find('input')

    await input.setValue('22/08/2026')
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual(['2026-08-22'])
    expect((input.element as HTMLInputElement).value).toBe('22/08/2026')
  })

  it('accepts 2-digit years when typing', async () => {
    const wrapper = mountField()
    const input = wrapper.find('input')

    await input.setValue('1/2/26')
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual(['2026-02-01'])
  })

  it('keeps unparsable text visible and flags invalid', async () => {
    const wrapper = mountField({ modelValue: '2026-08-22' })
    const input = wrapper.find('input')

    await input.setValue('not a date')
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual([''])
    expect((input.element as HTMLInputElement).value).toBe('not a date')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('.ui-field-error').text()).toBe('Not a valid date')
  })

  it('selecting a day in the grid commits and closes', async () => {
    const wrapper = mountField({ modelValue: '2026-08-10' })
    await wrapper.find('button[aria-label="Toggle calendar"]').trigger('click')

    const cell = wrapper.findAll('td[role="gridcell"] button').find((b) => b.text() === '22')!
    await cell.trigger('click')

    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual(['2026-08-22'])
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('Escape while open reverts the draft and closes', async () => {
    const wrapper = mountField({ modelValue: '2026-08-22' })
    const input = wrapper.find('input')

    await input.setValue('01/01/2001')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

    await input.trigger('keydown', { key: 'Escape' })

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect((input.element as HTMLInputElement).value).toBe('22/08/2026')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('does not re-emit on programmatic value change', async () => {
    const wrapper = mountField({ modelValue: '2026-08-22' })

    await wrapper.setProps({ modelValue: '2026-01-15' })

    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('15/01/2026')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('clearing the input commits an empty value', async () => {
    const wrapper = mountField({ modelValue: '2026-08-22' })
    const input = wrapper.find('input')

    await input.setValue('')
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual([''])
  })

  it('disables out-of-range days', async () => {
    const wrapper = mountField({ modelValue: '2026-08-10', min: '2026-08-05', max: '2026-08-20' })
    await wrapper.find('button[aria-label="Toggle calendar"]').trigger('click')

    const day3 = wrapper.findAll('td[role="gridcell"]').find((td) => td.text() === '3')!
    const day22 = wrapper.findAll('td[role="gridcell"]').find((td) => td.text() === '22')!

    expect(day3.attributes('aria-disabled')).toBe('true')
    expect(day22.attributes('aria-disabled')).toBe('true')

    await day22.find('button').trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('marks the selected day in the grid', async () => {
    const wrapper = mountField({ modelValue: '2026-08-22' })
    await wrapper.find('button[aria-label="Toggle calendar"]').trigger('click')

    const selected = wrapper.findAll('td[aria-selected="true"]')
    expect(selected).toHaveLength(1)
    expect(selected[0].text()).toBe('22')
  })

  it('paging preserves the anchored day across short months', async () => {
    const wrapper = mountField({ modelValue: '2026-01-31' })
    await wrapper.find('button[aria-label="Toggle calendar"]').trigger('click')

    const cell = () => wrapper.findAll('td[role="gridcell"] button').find((b) => b.attributes('tabindex') === '0')!

    await cell().trigger('keydown', { key: 'PageDown' })
    expect(wrapper.text()).toContain('February 2026')
    expect(cell().text()).toBe('28')

    await cell().trigger('keydown', { key: 'PageDown' })
    expect(wrapper.text()).toContain('March 2026')
    expect(cell().text()).toBe('31')
  })

  it('Shift+PageDown moves a year', async () => {
    const wrapper = mountField({ modelValue: '2026-08-22' })
    await wrapper.find('button[aria-label="Toggle calendar"]').trigger('click')

    const cell = wrapper.findAll('td[role="gridcell"] button').find((b) => b.attributes('tabindex') === '0')!
    await cell.trigger('keydown', { key: 'PageDown', shiftKey: true })

    expect(wrapper.text()).toContain('August 2027')
  })

  it('arrow keys move the focused day', async () => {
    const wrapper = mountField({ modelValue: '2026-08-22' })
    await wrapper.find('button[aria-label="Toggle calendar"]').trigger('click')

    const cell = () => wrapper.findAll('td[role="gridcell"] button').find((b) => b.attributes('tabindex') === '0')!
    await cell().trigger('keydown', { key: 'ArrowRight' })
    expect(cell().text()).toBe('23')

    await cell().trigger('keydown', { key: 'ArrowUp' })
    expect(cell().text()).toBe('16')
  })

  it('Enter on a focused cell selects it', async () => {
    const wrapper = mountField({ modelValue: '2026-08-22' })
    await wrapper.find('button[aria-label="Toggle calendar"]').trigger('click')

    const cell = () => wrapper.findAll('td[role="gridcell"] button').find((b) => b.attributes('tabindex') === '0')!
    await cell().trigger('keydown', { key: 'ArrowRight' })
    await cell().trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual(['2026-08-23'])
  })
})
