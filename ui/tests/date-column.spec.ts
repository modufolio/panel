import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DateColumn from '../src/Components/Columns/DateColumn.vue'

/**
 * Regression: the formatter used to swap each token for a `__PLACEHOLDER_n__`
 * marker and substitute afterwards. The literal word "PLACEHOLDER" contains
 * D, H and A, so the single-character tokens matched inside markers already
 * written and shredded them — even the default 'MMM D, YYYY' came out as
 * `__PLPMCE__PL__PLACEHOLDER_16__…`.
 */
const value = '2026-08-02T13:48:00'

function render(props: Record<string, unknown>) {
  return mount(DateColumn, { props: { value, ...props } }).text()
}

describe('DateColumn formatting', () => {
  it('formats the default pattern', () => {
    expect(render({ format: 'MMM D, YYYY' })).toBe('Aug 2, 2026')
  })

  it.each([
    ['MMM D, YYYY HH:mm', 'Aug 2, 2026 13:48'],
    ['DD/MM/YYYY', '02/08/2026'],
    ['YYYY-MM-DD', '2026-08-02'],
    ['MMMM D, YYYY', 'August 2, 2026'],
    ['MMMM D, YYYY h:mm A', 'August 2, 2026 1:48 PM'],
    ['HH:mm:ss', '13:48:00'],
    ['D/M/YY', '2/8/26'],
  ])('formats %s', (format, expected) => {
    expect(render({ format })).toBe(expected)
  })

  it('never leaks an internal placeholder into the output', () => {
    for (const format of ['MMM D, YYYY', 'MMM D, YYYY HH:mm', 'MMMM D, YYYY h:mm A']) {
      expect(render({ format })).not.toContain('PLACEHOLDER')
      expect(render({ format })).not.toContain('__')
    }
  })

  it('leaves literal separators untouched', () => {
    // Only known tokens are substituted; punctuation passes through.
    expect(render({ format: 'DD.MM.YYYY' })).toBe('02.08.2026')
  })
})
