import { describe, expect, it } from 'vitest'
import { niceSize } from '../src/index'

describe('niceSize', () => {
  it('climbs the unit ladder in powers of 1024', () => {
    expect(niceSize(512)).toBe('512 B')
    expect(niceSize(1024)).toBe('1 KB')
    expect(niceSize(1024 * 1024)).toBe('1 MB')
    expect(niceSize(1024 ** 3)).toBe('1 GB')
    expect(niceSize(1024 ** 4)).toBe('1 TB')
  })

  it('keeps at most two decimals, with no trailing zeros', () => {
    expect(niceSize(1536)).toBe('1.5 KB')
    expect(niceSize(1234567)).toBe('1.18 MB')
  })

  it('answers 0 KB for nothing, the way F::niceSize() does', () => {
    expect(niceSize(0)).toBe('0 KB')
    expect(niceSize(-1)).toBe('0 KB')
    expect(niceSize(null)).toBe('0 KB')
    expect(niceSize(undefined)).toBe('0 KB')
    expect(niceSize(Number.NaN)).toBe('0 KB')
  })

  it('formats the number for a locale, or leaves it alone', () => {
    expect(niceSize(1234567, 'de-DE')).toBe('1,18 MB')
    expect(niceSize(1234567, false)).toBe('1.18 MB')
  })

  it('does not run off the end of the ladder', () => {
    expect(niceSize(1024 ** 7)).toContain('PB')
  })
})
