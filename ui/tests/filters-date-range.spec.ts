import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { DateRangeFilter } from '../src/index'

/**
 * The presets are date arithmetic against "today", so the clock is frozen for
 * every test here — otherwise "Last 7 days" is a different answer depending on
 * when the suite runs, and "This month" is untestable on the first of a month.
 *
 * The frozen day is deliberately mid-month and mid-week, so a preset that
 * silently clamped to a month boundary would still show up.
 */
const TODAY = '2026-09-17'

beforeEach(() => {
  vi.useFakeTimers()
  // Local midday, not UTC midday: the presets are calendar days in the
  // viewer's own timezone, so freezing an instant that is already tomorrow in
  // UTC+13 would test the fixture rather than the arithmetic. Without the `Z`
  // this parses as local time, which puts every machine on the same day.
  vi.setSystemTime(new Date(`${TODAY}T12:00:00`))
})

afterEach(() => {
  vi.useRealTimers()
})

function filter(modelValue: Record<string, unknown> | undefined = undefined, override: Record<string, unknown> = {}) {
  return mount(DateRangeFilter, {
    props: { label: 'Screened', ...(modelValue ? { modelValue } : {}), ...override },
  })
}

const dates = (wrapper: ReturnType<typeof filter>) => wrapper.findAll('input[type="date"]')
const value = (wrapper: ReturnType<typeof filter>, index: number) =>
  (dates(wrapper)[index]!.element as HTMLInputElement).value

const preset = (wrapper: ReturnType<typeof filter>, label: string) =>
  wrapper.findAll('button').find((button) => button.text() === label)!

function emitted(wrapper: ReturnType<typeof filter>): Record<string, unknown> {
  const events = wrapper.emitted('update:modelValue')
  expect(events, 'expected the filter to emit').toBeTruthy()
  return events!.at(-1)![0] as Record<string, unknown>
}

describe('DateRangeFilter', () => {
  it('starts empty, with a From and a To', () => {
    const wrapper = filter()

    expect(dates(wrapper)).toHaveLength(2)
    expect(value(wrapper, 0)).toBe('')
    expect(value(wrapper, 1)).toBe('')
  })

  it('shows the range it was handed', () => {
    const wrapper = filter({ start: '2026-01-01', end: '2026-01-31' })

    expect(value(wrapper, 0)).toBe('2026-01-01')
    expect(value(wrapper, 1)).toBe('2026-01-31')
  })

  it('emits both ends, with the unset one as null rather than an empty string', async () => {
    const wrapper = filter()

    await dates(wrapper)[0]!.setValue('2026-03-01')

    expect(emitted(wrapper)).toEqual({ start: '2026-03-01', end: null })
  })

  it('keeps the two ends from crossing', () => {
    const wrapper = filter({ start: '2026-03-01', end: '2026-03-31' })

    expect(dates(wrapper)[0]!.attributes('max')).toBe('2026-03-31')
    expect(dates(wrapper)[1]!.attributes('min')).toBe('2026-03-01')
  })

  it('falls back to the declared bounds while an end is unset', () => {
    const wrapper = filter(undefined, { min: '2020-01-01', max: '2030-12-31' })

    expect(dates(wrapper)[0]!.attributes('max')).toBe('2030-12-31')
    expect(dates(wrapper)[1]!.attributes('min')).toBe('2020-01-01')
  })

  describe('presets', () => {
    it('are the five the panel ships with', () => {
      const labels = filter().findAll('.flex-wrap button').map((button) => button.text())

      expect(labels).toEqual(['Today', 'Last 7 days', 'Last 30 days', 'This month', 'Last month'])
    })

    it('can be replaced wholesale', () => {
      const wrapper = filter(undefined, { presets: [{ label: 'Last 3 days', days: 3 }] })

      expect(wrapper.findAll('.flex-wrap button').map((b) => b.text())).toEqual(['Last 3 days'])
    })

    it('can be turned off', () => {
      expect(filter(undefined, { showPresets: false }).find('.flex-wrap').exists()).toBe(false)
    })

    it('Today is the single day, both ends', async () => {
      const wrapper = filter()

      await preset(wrapper, 'Today').trigger('click')

      expect(emitted(wrapper)).toEqual({ start: TODAY, end: TODAY })
    })

    it('Last 7 days counts today as one of the seven', async () => {
      const wrapper = filter()

      await preset(wrapper, 'Last 7 days').trigger('click')

      // 11th through 17th inclusive is seven days, not eight.
      expect(emitted(wrapper)).toEqual({ start: '2026-09-11', end: TODAY })
    })

    it('Last 30 days reaches back into the previous month', async () => {
      const wrapper = filter()

      await preset(wrapper, 'Last 30 days').trigger('click')

      expect(emitted(wrapper)).toEqual({ start: '2026-08-19', end: TODAY })
    })

    it('This month runs from the first to today, not to the month end', async () => {
      const wrapper = filter()

      await preset(wrapper, 'This month').trigger('click')

      expect(emitted(wrapper)).toEqual({ start: '2026-09-01', end: TODAY })
    })

    it('Last month is the whole of it, ending on its last day', async () => {
      const wrapper = filter()

      await preset(wrapper, 'Last month').trigger('click')

      expect(emitted(wrapper)).toEqual({ start: '2026-08-01', end: '2026-08-31' })
    })

    it('marks the one whose range is currently in force', () => {
      const wrapper = filter({ start: '2026-09-01', end: TODAY })

      expect(preset(wrapper, 'This month').classes()).toContain('border-primary')
      expect(preset(wrapper, 'Today').classes()).not.toContain('border-primary')
    })

    it('marks none when the range was typed by hand', () => {
      const wrapper = filter({ start: '2026-09-03', end: '2026-09-05' })

      const active = wrapper.findAll('.flex-wrap button').filter((b) => b.classes().includes('border-primary'))
      expect(active).toHaveLength(0)
    })

    it('replaces a range already set rather than extending it', async () => {
      const wrapper = filter({ start: '2020-01-01', end: '2020-12-31' })

      await preset(wrapper, 'Today').trigger('click')

      expect(emitted(wrapper)).toEqual({ start: TODAY, end: TODAY })
    })
  })

  describe('clearing', () => {
    it('is offered once either end is set', () => {
      expect(filter().text()).not.toContain('Clear Filter')
      expect(filter({ start: '2026-01-01', end: null }).text()).toContain('Clear Filter')
      expect(filter({ start: null, end: '2026-01-01' }).text()).toContain('Clear Filter')
    })

    it('empties both ends', async () => {
      const wrapper = filter({ start: '2026-01-01', end: '2026-01-31' })

      await wrapper.findAll('button').at(-1)!.trigger('click')

      expect(emitted(wrapper)).toEqual({ start: null, end: null })
      expect(value(wrapper, 0)).toBe('')
    })
  })

  it('re-syncs from a range changed outside', async () => {
    const wrapper = filter({ start: '2026-01-01', end: '2026-01-31' })

    await wrapper.setProps({ modelValue: { start: null, end: null } })

    expect(value(wrapper, 0)).toBe('')
    expect(wrapper.text()).not.toContain('Clear Filter')
  })
})
