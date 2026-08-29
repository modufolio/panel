/**
 * Field validation for blueprint forms.
 *
 * Rules are plain functions returning `true` or a message. There is no string
 * mini-language ('min:3|max:5'): in TypeScript a parsed string throws away
 * every bit of inference, while `min(3)` is checked at the call site and
 * autocompletes.
 *
 * A rule never throws and never mutates — given a value and the surrounding
 * form it answers one question, so rules stay trivially testable and can be
 * evaluated in any order.
 */

export type ValidationRule = (value: unknown, form: Record<string, unknown>) => string | true

const isBlank = (value: unknown): boolean =>
  value === undefined || value === null || value === '' ||
  (Array.isArray(value) && value.length === 0)

/**
 * One measure for every kind of value, so a single `min`/`max` pair covers
 * text length, numeric magnitude and collection size — the caller does not
 * pick a different rule per type.
 */
function sizeOf(value: unknown): number {
  if (typeof value === 'number') return value
  if (typeof value === 'boolean') return value ? 1 : 0
  if (Array.isArray(value)) return value.length
  return String(value ?? '').length
}

/** Describes what `min`/`max` are measuring, so the message reads correctly. */
function sizeNoun(value: unknown): string {
  if (typeof value === 'number') return 'value'
  if (Array.isArray(value)) return 'selection'
  return 'length'
}

export const required = (message = 'This field is required.'): ValidationRule =>
  (value) => (isBlank(value) ? message : true)

/**
 * Every rule below passes on a blank value. Emptiness is `required`'s business
 * alone — otherwise an optional field would report a format error simply for
 * being left alone.
 */
const optional = (check: ValidationRule): ValidationRule =>
  (value, form) => (isBlank(value) ? true : check(value, form))

export const min = (limit: number, message?: string): ValidationRule =>
  optional((value) =>
    sizeOf(value) >= limit
      ? true
      : message ?? `Minimum ${sizeNoun(value)} is ${limit}.`
  )

export const max = (limit: number, message?: string): ValidationRule =>
  optional((value) =>
    sizeOf(value) <= limit
      ? true
      : message ?? `Maximum ${sizeNoun(value)} is ${limit}.`
  )

// Deliberately permissive: something@something.something. Stricter patterns
// reject addresses that are perfectly valid, and the real check is whether
// mail arrives.
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export const email = (message = 'Enter a valid email address.'): ValidationRule =>
  optional((value) => (EMAIL.test(String(value)) ? true : message))

export const url = (message = 'Enter a valid URL.'): ValidationRule =>
  optional((value) => {
    try {
      new URL(String(value))
      return true
    } catch {
      return message
    }
  })

export const pattern = (regex: RegExp, message = 'This value is not in the expected format.'): ValidationRule =>
  optional((value) => (regex.test(String(value)) ? true : message))

export const integer = (message = 'Enter a whole number.'): ValidationRule =>
  optional((value) => (Number.isInteger(Number(value)) ? true : message))

/** Matches another field — confirm-password and the like. */
export const same = (otherKey: string, message?: string): ValidationRule =>
  (value, form) =>
    value === form[otherKey] ? true : message ?? `Must match ${otherKey}.`

/**
 * Build rules from a serialised spec, as a server-side blueprint declares them:
 *
 *     ['required' => true, 'max' => 300, 'email' => true]
 *
 * This is what lets a field be described once, on the server, and still be
 * checked before a round trip. The server remains the authority — this only
 * spares the user a submit to learn something already knowable.
 *
 * Unknown rule names are ignored rather than throwing: the server may know
 * rules this client build does not, and refusing to render a form over one
 * unrecognised key would be a much worse failure than skipping it.
 */
export function rulesFromSpec(spec: Record<string, unknown> | undefined): ValidationRule[] {
  const rules: ValidationRule[] = []

  if (spec === undefined) {
    return rules
  }

  // `required` first so an empty value reports absence rather than format.
  if (spec.required === true) {
    rules.push(required())
  }

  for (const [name, param] of Object.entries(spec)) {
    switch (name) {
      case 'min': rules.push(min(Number(param))); break
      case 'max': rules.push(max(Number(param))); break
      case 'email': if (param === true) rules.push(email()); break
      case 'url': if (param === true) rules.push(url()); break
      case 'integer': if (param === true) rules.push(integer()); break
      case 'pattern': {
        const parsed = parseDelimitedRegex(param)
        if (parsed !== null) rules.push(pattern(parsed))
        break
      }
    }
  }

  return rules
}

/**
 * PCRE patterns arrive delimited (`/^[a-z-]+$/i`) because that is how PHP
 * writes them. Strip the delimiters and carry over the flags JavaScript shares;
 * anything unparseable yields null so the rule is skipped rather than throwing
 * during render.
 */
function parseDelimitedRegex(value: unknown): RegExp | null {
  if (typeof value !== 'string') {
    return null
  }

  const match = /^([/#~])(.*)\1([a-z]*)$/s.exec(value)

  try {
    return match === null
      ? new RegExp(value)
      : new RegExp(match[2], match[3].replace(/[^gimsuy]/g, ''))
  } catch {
    return null
  }
}

/**
 * First failing message for a value, or null.
 *
 * Only the first is returned: a field shows one message at a time, and running
 * on would report "too short" about a value the user has not finished typing.
 */
export function firstError(
  rules: ValidationRule[] | undefined,
  value: unknown,
  form: Record<string, unknown>,
): string | null {
  for (const rule of rules ?? []) {
    const result = rule(value, form)
    if (result !== true) {
      return result
    }
  }

  return null
}
