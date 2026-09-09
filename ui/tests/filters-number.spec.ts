import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { NumberFilter } from '../src/index'

/**
 * A number filter is an operator plus one or two values, and the operator is
 * what decides which. It keeps its own copy of both and re-syncs from the
 * prop, so the payload shape has to change with the operator rather than
 * carrying a stale `value` beside a range.
 */

function filter(modelValue: Record<string, unknown> | undefined = undefined, override: Record<string, unknown> = {}) {
  return mount(NumberFilter, {
    props: { label: 'Seats', ...(modelValue ? { modelValue } : {}), ...override },
  })
}

const operator = (wrapper: ReturnType<typeof filter>) => wrapper.find('select')
const numbers = (wrapper: ReturnType<typeof filter>) => wrapper.findAll('input[type="number"]')

function emitted(wrapper: ReturnType<typeof filter>): Record<string, unknown> {
  const events = wrapper.emitted('update:modelValue')
  expect(events, 'expected the filter to emit').toBeTruthy()
  return events!.at(-1)![0] as Record<string, unknown>
}

describe('NumberFilter', () => {
  it('offers the seven comparisons a number supports', () => {
    expect(operator(filter()).findAll('option').map((o) => o.attributes('value'))).toEqual([
      '=', '!=', '>', '>=', '<', '<=', 'between',
    ])
  })

  it('starts on = with one input', () => {
    const wrapper = filter()

    expect((operator(wrapper).element as HTMLSelectElement).value).toBe('=')
    expect(numbers(wrapper)).toHaveLength(1)
  })

  it('shows the operator and value it was handed', () => {
    const wrapper = filter({ operator: '>=', value: 12 })

    expect((operator(wrapper).element as HTMLSelectElement).value).toBe('>=')
    expect((numbers(wrapper)[0]!.element as HTMLInputElement).value).toBe('12')
  })

  it('emits the operator with its value', async () => {
    const wrapper = filter()

    await numbers(wrapper)[0]!.setValue(7)

    expect(emitted(wrapper)).toEqual({ operator: '=', value: 7 })
  })

  describe('between', () => {
    it('swaps the single input for a min and a max', async () => {
      const wrapper = filter()

      await operator(wrapper).setValue('between')

      expect(numbers(wrapper)).toHaveLength(2)
      expect(wrapper.text()).toContain('Min')
      expect(wrapper.text()).toContain('Max')
    })

    it('emits a range rather than a value, so no stale single value rides along', async () => {
      const wrapper = filter({ operator: '=', value: 7 })

      await operator(wrapper).setValue('between')

      expect(emitted(wrapper)).toEqual({ operator: 'between', rangeMin: null, rangeMax: null })
      expect(emitted(wrapper)).not.toHaveProperty('value')
    })

    it('emits both bounds as they are typed', async () => {
      const wrapper = filter({ operator: 'between' })

      await numbers(wrapper)[0]!.setValue(2)
      await numbers(wrapper)[1]!.setValue(9)

      expect(emitted(wrapper)).toEqual({ operator: 'between', rangeMin: 2, rangeMax: 9 })
    })

    it('keeps the max from being set below the min, and vice versa', async () => {
      const wrapper = filter({ operator: 'between', rangeMin: 2, rangeMax: 9 })

      expect(numbers(wrapper)[0]!.attributes('max')).toBe('9')
      expect(numbers(wrapper)[1]!.attributes('min')).toBe('2')
    })

    it('emits a value rather than a range when the operator goes back', async () => {
      const wrapper = filter({ operator: 'between', rangeMin: 2, rangeMax: 9 })

      await operator(wrapper).setValue('<')

      expect(emitted(wrapper)).toEqual({ operator: '<', value: null })
    })
  })

  describe('bounds and step', () => {
    it('passes min, max and step to the input', () => {
      const wrapper = filter(undefined, { min: 1, max: 10, step: 0.5 })
      const input = numbers(wrapper)[0]!

      expect(input.attributes('min')).toBe('1')
      expect(input.attributes('max')).toBe('10')
      expect(input.attributes('step')).toBe('0.5')
    })

    it('steps by one unless told otherwise', () => {
      expect(numbers(filter())[0]!.attributes('step')).toBe('1')
    })
  })

  describe('clearing', () => {
    it('is offered only once there is something to clear', () => {
      expect(filter().text()).not.toContain('Clear Filter')
      expect(filter({ operator: '=', value: 3 }).text()).toContain('Clear Filter')
    })

    it('is offered when only one end of a range is set', () => {
      expect(filter({ operator: 'between', rangeMin: 2 }).text()).toContain('Clear Filter')
    })

    it('empties the value and returns the operator to =', async () => {
      const wrapper = filter({ operator: '>', value: 3 })

      await wrapper.find('button').trigger('click')

      expect(emitted(wrapper)).toEqual({ operator: '=', value: null })
    })
  })

  describe('re-syncing from the prop', () => {
    it('follows an operator changed outside', async () => {
      const wrapper = filter({ operator: '=', value: 1 })

      await wrapper.setProps({ modelValue: { operator: 'between', rangeMin: 2, rangeMax: 9 } })

      expect(numbers(wrapper)).toHaveLength(2)
      expect((numbers(wrapper)[0]!.element as HTMLInputElement).value).toBe('2')
    })

    it('drops a value cleared outside', async () => {
      const wrapper = filter({ operator: '=', value: 5 })

      await wrapper.setProps({ modelValue: { operator: '=', value: null } })

      expect((numbers(wrapper)[0]!.element as HTMLInputElement).value).toBe('')
      expect(wrapper.text()).not.toContain('Clear Filter')
    })
  })

  describe('zero is a value like any other', () => {
    it('is shown when it arrives as the initial value', () => {
      const wrapper = filter({ operator: '>', value: 0 })

      expect((numbers(wrapper)[0]!.element as HTMLInputElement).value).toBe('0')
    })

    it('counts as something to clear', () => {
      expect(filter({ operator: '>', value: 0 }).text()).toContain('Clear Filter')
    })

    it('survives as the min of a range', () => {
      const wrapper = filter({ operator: 'between', rangeMin: 0, rangeMax: 9 })

      expect((numbers(wrapper)[0]!.element as HTMLInputElement).value).toBe('0')
    })
  })
})
