/**
 * A byte count as a person reads it — the client's half of the server's
 * `F::niceSize()`.
 *
 * Same rules, so an upload does not change size when the page reloads and the
 * server starts answering instead: powers of 1024, the same unit ladder, at
 * most two decimals, locale number formatting. `0 KB` for nothing at all is
 * the server's answer too, odd as it looks next to `B`.
 */
const UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB']

/**
 * @param locale Locale for number formatting, `undefined` for the current one,
 *               `false` to leave the number unformatted.
 */
export function niceSize(bytes: number | null | undefined, locale?: string | false): string {
  const size = Number(bytes)

  if (!Number.isFinite(size) || size <= 0) {
    return '0 KB'
  }

  const unit = Math.min(Math.floor(Math.log(size) / Math.log(1024)), UNITS.length - 1)
  const value = Math.round((size / 1024 ** unit) * 100) / 100

  const formatted = locale === false
    ? String(value)
    : new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(value)

  return `${formatted} ${UNITS[unit]}`
}
