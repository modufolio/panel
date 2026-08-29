import { describe, it, expect } from 'vitest'
import {
  addDays, addMonths, adjustTwoDigitYear, atMidnight, clampToRange,
  dateAllowed, dateEquals, daysInMonth, formatDisplay, formatISO,
  makeDate, monthMatrix, parseISO, parseUserInput,
} from '../src/Utils/dates'

describe('dates', () => {
  describe('parseISO / formatISO', () => {
    it('round-trips a plain date', () => {
      const date = parseISO('2026-08-22')
      expect(date).not.toBeNull()
      expect(formatISO(date!)).toBe('2026-08-22')
    })

    it('parses as local midnight, not UTC', () => {
      const date = parseISO('2026-03-05')!
      expect(date.getDate()).toBe(5)
      expect(date.getHours()).toBe(0)
    })

    it('rejects overflow dates', () => {
      expect(parseISO('2026-02-31')).toBeNull()
      expect(parseISO('2026-13-01')).toBeNull()
    })

    it('rejects non-ISO text', () => {
      expect(parseISO('22/08/2026')).toBeNull()
      expect(parseISO('')).toBeNull()
      expect(parseISO('2026-8-2')).toBeNull()
    })

    it('handles years 0-99 without mapping to 1900s', () => {
      expect(parseISO('0099-01-01')!.getFullYear()).toBe(99)
    })
  })

  describe('dateEquals', () => {
    it('ignores time of day', () => {
      const a = new Date(2026, 7, 22, 15, 30)
      const b = new Date(2026, 7, 22, 3, 0)
      expect(dateEquals(a, b)).toBe(true)
    })

    it('two nulls are not equal', () => {
      expect(dateEquals(null, null)).toBe(false)
    })
  })

  describe('addMonths', () => {
    it('clamps instead of overflowing', () => {
      expect(formatISO(addMonths(makeDate(2026, 0, 31), 1))).toBe('2026-02-28')
    })

    it('keeps the anchor day across short months', () => {
      const feb = addMonths(makeDate(2026, 0, 31), 1, 31)
      expect(formatISO(feb)).toBe('2026-02-28')
      const mar = addMonths(feb, 1, 31)
      expect(formatISO(mar)).toBe('2026-03-31')
    })

    it('handles leap years', () => {
      expect(formatISO(addMonths(makeDate(2024, 0, 31), 1))).toBe('2024-02-29')
    })

    it('crosses year boundaries backwards', () => {
      expect(formatISO(addMonths(makeDate(2026, 0, 15), -1))).toBe('2025-12-15')
    })
  })

  describe('addDays', () => {
    it('crosses month and year boundaries', () => {
      expect(formatISO(addDays(makeDate(2025, 11, 31), 1))).toBe('2026-01-01')
    })
  })

  describe('constraints', () => {
    const min = makeDate(2026, 0, 10)
    const max = makeDate(2026, 0, 20)

    it('dateAllowed enforces the range inclusively', () => {
      expect(dateAllowed(makeDate(2026, 0, 10), min, max)).toBe(true)
      expect(dateAllowed(makeDate(2026, 0, 20), min, max)).toBe(true)
      expect(dateAllowed(makeDate(2026, 0, 9), min, max)).toBe(false)
      expect(dateAllowed(makeDate(2026, 0, 21), min, max)).toBe(false)
    })

    it('dateAllowed consults the callback', () => {
      const weekendsOff = (d: Date) => d.getDay() === 0 || d.getDay() === 6
      expect(dateAllowed(makeDate(2026, 0, 17), null, null, weekendsOff)).toBe(false)
      expect(dateAllowed(makeDate(2026, 0, 15), null, null, weekendsOff)).toBe(true)
    })

    it('clampToRange pulls outside dates to the edge', () => {
      expect(formatISO(clampToRange(makeDate(2026, 0, 1), min, max))).toBe('2026-01-10')
      expect(formatISO(clampToRange(makeDate(2026, 5, 1), min, max))).toBe('2026-01-20')
      expect(formatISO(clampToRange(makeDate(2026, 0, 15), min, max))).toBe('2026-01-15')
    })
  })

  describe('adjustTwoDigitYear', () => {
    const ref = makeDate(2026, 0, 1)

    it('picks the century within ±50 years of the reference', () => {
      expect(adjustTwoDigitYear(40, ref)).toBe(2040)
      expect(adjustTwoDigitYear(90, ref)).toBe(1990)
      expect(adjustTwoDigitYear(0, ref)).toBe(2000)
    })

    it('depends on the reference date', () => {
      expect(adjustTwoDigitYear(40, makeDate(1985, 0, 1))).toBe(1940)
    })
  })

  describe('monthMatrix', () => {
    it('produces full weeks with null padding', () => {
      const weeks = monthMatrix(2026, 7, 1) // August 2026 starts on a Saturday
      expect(weeks.every((w) => w.length === 7)).toBe(true)
      expect(weeks[0].slice(0, 5).every((c) => c === null)).toBe(true)
      expect(weeks[0][5]!.getDate()).toBe(1)
    })

    it('respects firstDayOfWeek', () => {
      const mondayFirst = monthMatrix(2026, 7, 1)
      const sundayFirst = monthMatrix(2026, 7, 0)
      expect(mondayFirst[0].filter((c) => c !== null)).toHaveLength(2) // Sat, Sun
      expect(sundayFirst[0].filter((c) => c !== null)).toHaveLength(1) // Sat
    })

    it('covers every day exactly once', () => {
      const days = monthMatrix(2026, 1, 1).flat().filter((c): c is Date => c !== null)
      expect(days).toHaveLength(daysInMonth(2026, 1))
      expect(days[0].getDate()).toBe(1)
      expect(days[days.length - 1].getDate()).toBe(28)
    })
  })

  describe('parseUserInput', () => {
    const ref = makeDate(2026, 0, 1)

    it('accepts day-first with slash, dash and dot', () => {
      expect(formatISO(parseUserInput('22/08/2026', ref)!)).toBe('2026-08-22')
      expect(formatISO(parseUserInput('22-8-2026', ref)!)).toBe('2026-08-22')
      expect(formatISO(parseUserInput('22.8.2026', ref)!)).toBe('2026-08-22')
    })

    it('accepts strict ISO too', () => {
      expect(formatISO(parseUserInput('2026-08-22', ref)!)).toBe('2026-08-22')
    })

    it('resolves 2-digit years near the reference', () => {
      expect(formatISO(parseUserInput('1/1/40', ref)!)).toBe('2040-01-01')
      expect(formatISO(parseUserInput('1/1/90', ref)!)).toBe('1990-01-01')
    })

    it('rejects impossible and garbage input', () => {
      expect(parseUserInput('31/02/2026', ref)).toBeNull()
      expect(parseUserInput('soon', ref)).toBeNull()
      expect(parseUserInput('', ref)).toBeNull()
      expect(parseUserInput('1/2/3/4', ref)).toBeNull()
    })
  })

  describe('formatDisplay', () => {
    it('renders dd/mm/yyyy', () => {
      expect(formatDisplay(makeDate(2026, 7, 5))).toBe('05/08/2026')
    })
  })

  describe('atMidnight', () => {
    it('drops the time', () => {
      expect(atMidnight(new Date(2026, 7, 22, 23, 59)).getHours()).toBe(0)
    })
  })
})
