/**
 * Pure date math for the date picker. No DOM, no timezones beyond "local".
 *
 * Every Date produced here is a local-time midnight. Dates are constructed
 * through setters rather than string or numeric constructor arguments:
 * `new Date('2026-03-05')` parses as UTC midnight (off by a day west of
 * Greenwich) and `new Date(26, 2, 5)` maps year 26 to 1926.
 */

/** Build a local-midnight Date. Month is 0-based, like the Date API. */
export function makeDate(year: number, month: number, day: number): Date {
  const date = new Date(0, 0)
  date.setFullYear(year, month, day)
  date.setHours(0, 0, 0, 0)
  return date
}

/** Strict `YYYY-MM-DD`. Rejects overflow like 2026-02-31. Null for anything else. */
export function parseISO(value: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  if (match === null) {
    return null
  }

  const [, year, month, day] = match.map(Number)
  const date = makeDate(year, month - 1, day)

  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day
    ? date
    : null
}

export function formatISO(date: Date): string {
  const pad = (n: number, width: number) => String(n).padStart(width, '0')
  return `${pad(date.getFullYear(), 4)}-${pad(date.getMonth() + 1, 2)}-${pad(date.getDate(), 2)}`
}

/** Local midnight of the same calendar day. */
export function atMidnight(date: Date): Date {
  return makeDate(date.getFullYear(), date.getMonth(), date.getDate())
}

export function todayMidnight(): Date {
  return atMidnight(new Date())
}

/** Same calendar day, ignoring time. Null-safe: two nulls are not equal. */
export function dateEquals(a: Date | null, b: Date | null): boolean {
  return a !== null && b !== null && atMidnight(a).getTime() === atMidnight(b).getTime()
}

export function addDays(date: Date, days: number): Date {
  const result = atMidnight(date)
  result.setDate(result.getDate() + days)
  return result
}

/**
 * Month arithmetic that clamps instead of overflowing: Jan 31 + 1 month is
 * Feb 28/29, not Mar 3. `anchorDay` lets a caller keep the originally chosen
 * day across repeated jumps, so Jan 31 → Feb 28 → Mar 31 rather than Mar 28.
 */
export function addMonths(date: Date, months: number, anchorDay?: number): Date {
  const day = anchorDay ?? date.getDate()
  const result = makeDate(date.getFullYear(), date.getMonth() + months, day)

  // Overflowed into the next month — clamp to the last day of the target one.
  if (result.getDate() !== day) {
    result.setDate(0)
  }

  return result
}

export function startOfMonth(date: Date): Date {
  return makeDate(date.getFullYear(), date.getMonth(), 1)
}

export function daysInMonth(year: number, month: number): number {
  return makeDate(year, month + 1, 0).getDate()
}

/** Is `date` within [min, max] and not vetoed by the callback? */
export function dateAllowed(
  date: Date,
  min: Date | null,
  max: Date | null,
  isDisabled?: (date: Date) => boolean,
): boolean {
  const time = atMidnight(date).getTime()

  if (min !== null && time < atMidnight(min).getTime()) return false
  if (max !== null && time > atMidnight(max).getTime()) return false

  return isDisabled === undefined || !isDisabled(date)
}

/** Nearest date inside [min, max]; the date itself when already inside. */
export function clampToRange(date: Date, min: Date | null, max: Date | null): Date {
  const time = atMidnight(date).getTime()

  if (min !== null && time < atMidnight(min).getTime()) return atMidnight(min)
  if (max !== null && time > atMidnight(max).getTime()) return atMidnight(max)

  return atMidnight(date)
}

/**
 * Resolve a 2-digit year to the century that lands it within ±50 years of
 * the reference date: "1/1/40" means 2040 next to 2026, 1940 next to 1985.
 */
export function adjustTwoDigitYear(year: number, reference: Date): number {
  const referenceYear = reference.getFullYear()
  const candidate = Math.floor(referenceYear / 100) * 100 + year

  if (candidate < referenceYear - 50) return candidate + 100
  if (candidate > referenceYear + 50) return candidate - 100

  return candidate
}

/**
 * The weeks of one month as rows of 7. Leading/trailing cells outside the
 * month are null (rendered empty, not as adjacent-month days).
 * `firstDayOfWeek`: 0 = Sunday, 1 = Monday.
 */
export function monthMatrix(year: number, month: number, firstDayOfWeek = 1): Array<Array<Date | null>> {
  const first = makeDate(year, month, 1)
  const lead = (first.getDay() - firstDayOfWeek + 7) % 7
  const total = daysInMonth(year, month)

  const cells: Array<Date | null> = Array<Date | null>(lead).fill(null)
  for (let day = 1; day <= total; day++) {
    cells.push(makeDate(year, month, day))
  }
  while (cells.length % 7 !== 0) {
    cells.push(null)
  }

  const weeks: Array<Array<Date | null>> = []
  for (let i = 0; i < cells.length; i += 7) {
    weeks.push(cells.slice(i, i + 7))
  }

  return weeks
}

/**
 * Lenient user-input parsing: d/m/y, d-m-y, d.m.y, with 2- or 4-digit years
 * (2-digit resolved near `reference`), plus strict ISO. Day-first, matching
 * the panel's display format. Null when the text is not a real date.
 */
export function parseUserInput(text: string, reference: Date = todayMidnight()): Date | null {
  const trimmed = text.trim()
  if (trimmed === '') {
    return null
  }

  const iso = parseISO(trimmed)
  if (iso !== null) {
    return iso
  }

  const match = /^(\d{1,2})[./-](\d{1,2})[./-](\d{1,4})$/.exec(trimmed)
  if (match === null) {
    return null
  }

  const day = Number(match[1])
  const month = Number(match[2])
  let year = Number(match[3])

  if (match[3].length <= 2) {
    year = adjustTwoDigitYear(year, reference)
  }

  const date = makeDate(year, month - 1, day)

  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day
    ? date
    : null
}

/** Display format for the panel: `dd/mm/yyyy`. */
export function formatDisplay(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`
}
