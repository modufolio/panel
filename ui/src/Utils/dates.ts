/**
 * Pure date math and date *display*, shared by the picker, the table's date
 * column and the drawer's field grid — one definition, so the same moment
 * cannot read three ways in three places. No DOM, no timezones beyond
 * "local".
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

// ── Display ──────────────────────────────────────────────────────────────────

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const FULL_MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

/**
 * A date in a `YYYY`/`MMM`/`DD`/`HH`/`mm` template, the tokens a resource
 * writes in `Column::format()`.
 */
export function formatDate(date: Date, format: string): string {
  const d = date.getDate()
  const m = date.getMonth()
  const y = date.getFullYear()
  const h = date.getHours()
  const min = date.getMinutes()
  const s = date.getSeconds()

  const tokens: Record<string, string | number> = {
    'YYYY': y,
    'YY': String(y).slice(-2),
    'MMMM': FULL_MONTHS[m],
    'MMM': MONTHS[m],
    'MM': String(m + 1).padStart(2, '0'),
    'M': m + 1,
    'DD': String(d).padStart(2, '0'),
    'D': d,
    'HH': String(h).padStart(2, '0'),
    'H': h,
    'hh': String(h % 12 || 12).padStart(2, '0'),
    'h': h % 12 || 12,
    'mm': String(min).padStart(2, '0'),
    'm': min,
    'ss': String(s).padStart(2, '0'),
    's': s,
    'A': h >= 12 ? 'PM' : 'AM',
    'a': h >= 12 ? 'pm' : 'am',
  }

  // Single pass, longest-token-first alternation.
  //
  // An earlier implementation swapped each token for a `__PLACEHOLDER_n__`
  // marker and substituted afterwards — but the literal word "PLACEHOLDER"
  // contains D, H and A, so the single-character tokens matched *inside*
  // markers already written and shredded them. Even the default
  // 'MMM D, YYYY' came out mangled. Replacing in one pass means no output is
  // ever rescanned.
  const pattern = /YYYY|YY|MMMM|MMM|MM|M|DD|D|HH|H|hh|h|mm|m|ss|s|A|a/g

  return format.replace(pattern, (token: string) => String(tokens[token]))
}

/** "just now", "3 hours ago", "2 years ago". */
export function relativeTime(date: Date, now: Date = new Date()): string {
  const seconds = Math.floor((now.getTime() - date.getTime()) / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)
  const weeks = Math.floor(days / 7)
  const months = Math.floor(days / 30)
  const years = Math.floor(days / 365)

  const plural = (count: number, unit: string) => `${count} ${count === 1 ? unit : `${unit}s`} ago`

  if (seconds < 60) return 'just now'
  if (minutes < 60) return plural(minutes, 'minute')
  if (hours < 24) return plural(hours, 'hour')
  if (days < 7) return plural(days, 'day')
  if (weeks < 4) return plural(weeks, 'week')
  if (months < 12) return plural(months, 'month')

  return plural(years, 'year')
}

/**
 * A timestamp the server sent, as a Date — or null when the string is not one.
 *
 * Strict on purpose: this decides whether a *presented* value is a date at
 * all, so anything looser would reformat text that merely starts with digits.
 * A date without a time is read as local midnight, since `new Date('…-09-08')`
 * is UTC midnight and renders as the day before west of Greenwich.
 */
export function parseTimestamp(value: string): Date | null {
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return parseISO(value)
  }

  if (!/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?$/.test(value)) {
    return null
  }

  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? null : date
}

/** Whether a timestamp carries a time of day, or is a plain calendar date. */
export function hasTimeOfDay(value: string): boolean {
  return !/^\d{4}-\d{2}-\d{2}$/.test(value)
}
