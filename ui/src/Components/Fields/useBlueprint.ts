import { computed, type ComputedRef } from 'vue'
import type { FieldWidth } from './useFieldWidth'
import { firstError, type ValidationRule } from './validation'

/** A value, or a function returning it — so callers can pass reactive sources. */
export type MaybeGetter<T> = T | (() => T)

// ── Field definition types ────────────────────────────────────────────────────

/** Field types shipped with the panel. */
export type BuiltinFieldType =
  | 'text'
  | 'textarea'
  | 'select'
  | 'multiselect'
  | 'toggle'
  | 'checkbox'
  | 'range'
  | 'date'
  | 'datetime'
  | 'time'
  | 'date-range'
  | 'file'
  | 'color'
  | 'belongs-to'
  | 'repeater'
  | 'toggle-buttons'

/**
 * Built-ins plus anything an application registers via registerFieldType()
 * (e.g. 'writer', 'rich-text', 'block-editor'). The `string & {}` keeps
 * autocomplete for built-ins while accepting custom types.
 */
export type FieldType = BuiltinFieldType | (string & {})

export interface OptionItem {
  label: string
  value: string | number | boolean
  disabled?: boolean
}

export interface FieldDef {
  /** Field type — maps to a field component */
  type: FieldType
  /** Form key — used for v-model binding */
  key: string
  /** Display label */
  label: string
  /** Grid column width (default: 'full') */
  width?: FieldWidth
  /** Mark field as required */
  required?: boolean
  /** Help text shown below the field */
  help?: string
  /** Placeholder text */
  placeholder?: string
  /** Options for select / multiselect / toggle-buttons */
  options?: OptionItem[]
  /** Extra props passed directly to the field component */
  props?: Record<string, unknown>
  /** Conditional visibility — the field is hidden when this evaluates false */
  when?: Condition
  /** Conditionally required — same shape as `when`, server-mirrored. */
  requiredWhen?: Condition
  /** Client-side validation, evaluated in order; the first failure is shown */
  rules?: ValidationRule[]
  /** Sub-field declarations, for container types such as `repeater` */
  fields?: FieldDef[]
}

// ── Conditions ────────────────────────────────────────────────────────────────

export type ConditionOperator =
  | '==' | '!=' | '>' | '>=' | '<' | '<='
  | 'in' | 'not_in' | 'contains'
  | 'empty' | 'not_empty'

/**
 * A single comparison against another field's value.
 *
 *   ['status', 'published']            // implicit '=='
 *   ['status', '!=', 'draft']
 *   ['cover', 'not_empty']
 */
export type ConditionTuple =
  | [key: string, operator: 'empty' | 'not_empty']
  | [key: string, operator: ConditionOperator, value: unknown]
  | [key: string, value: unknown]

/**
 * Conditions compose explicitly through `all` / `any` rather than by array
 * nesting depth. Depth-as-grammar reads compactly but is invisible to
 * TypeScript and easy to misread; naming the combinator costs a few characters
 * and keeps the shape checkable.
 */
export type Condition =
  | ((form: Record<string, unknown>) => boolean)
  | ConditionTuple
  | { all: Condition[] }
  | { any: Condition[] }
  | { not: Condition }

const isEmpty = (value: unknown): boolean =>
  value === undefined || value === null || value === '' ||
  (Array.isArray(value) && value.length === 0)

/**
 * Comparison operators. Extend this map to add one — the evaluator does no
 * other dispatch.
 */
const operators: Record<ConditionOperator, (actual: unknown, expected: unknown) => boolean> = {
  '==':        (a, b) => a === b,
  '!=':        (a, b) => a !== b,
  '>':         (a, b) => Number(a) > Number(b),
  '>=':        (a, b) => Number(a) >= Number(b),
  '<':         (a, b) => Number(a) < Number(b),
  '<=':        (a, b) => Number(a) <= Number(b),
  'in':        (a, b) => Array.isArray(b) && b.includes(a),
  'not_in':    (a, b) => Array.isArray(b) && !b.includes(a),
  // The field's own value is the collection — a multiselect holding `b`.
  'contains':  (a, b) => Array.isArray(a) ? a.includes(b) : String(a ?? '').includes(String(b)),
  'empty':     (a) => isEmpty(a),
  'not_empty': (a) => !isEmpty(a),
}

function evaluateTuple(tuple: ConditionTuple, form: Record<string, unknown>): boolean {
  const [key, second, third] = tuple as [string, unknown, unknown]
  const actual = form[key]

  // Arity sniffing: a two-element tuple is either a value-less operator
  // ('empty') or an implicit '=='.
  if (tuple.length === 2) {
    return second === 'empty' || second === 'not_empty'
      ? operators[second](actual, undefined)
      : operators['=='](actual, second)
  }

  const operator = operators[second as ConditionOperator]

  // An unknown operator hides nothing — failing open keeps a typo from making
  // a field silently vanish, which is far harder to notice than a stray field.
  return operator === undefined ? true : operator(actual, third)
}

/** Evaluate a condition against the current form values. */
export function evaluateCondition(condition: Condition, form: Record<string, unknown>): boolean {
  if (typeof condition === 'function') {
    return condition(form)
  }

  if (Array.isArray(condition)) {
    return evaluateTuple(condition, form)
  }

  if ('all' in condition) {
    return condition.all.every((member) => evaluateCondition(member, form))
  }

  if ('not' in condition) {
    return !evaluateCondition(condition.not, form)
  }

  return condition.any.some((member) => evaluateCondition(member, form))
}

// ── Component registry ────────────────────────────────────────────────────────

// Lives in fieldRegistry.ts so field components (the repeater renders
// sub-fields through it) can import the resolver without importing this
// composable back — re-exported here so consumers keep their import path.
export { registerFieldType, resolveFieldComponent } from './fieldRegistry'
import { resolveFieldComponent } from './fieldRegistry'

// ── useBlueprint composable ───────────────────────────────────────────────────

/**
 * Converts a FieldDef array into reactive field state for use with BlueprintForm.
 *
 * @param fields - Array of field definitions
 * @param form   - Reactive form object (e.g. from useForm())
 *
 * @example
 * const { visibleFields, fieldProps } = useBlueprint(fields, form)
 */
export function useBlueprint(
  fields: MaybeGetter<FieldDef[]>,
  form: MaybeGetter<Record<string, unknown>>,
): {
  visibleFields: ComputedRef<FieldDef[]>
  visibleData: ComputedRef<Record<string, unknown>>
  clientErrors: ComputedRef<Record<string, string>>
  isValid: ComputedRef<boolean>
  fieldProps: (field: FieldDef, errors?: Record<string, string>) => Record<string, unknown>
  resolveFieldComponent: typeof resolveFieldComponent
} {
  // Read through getters so the computeds below track the *current* values.
  // Taking plain objects here would capture whatever identity existed at setup,
  // and since consumers emit a fresh object on every change, every condition
  // would be evaluated against a stale snapshot forever.
  const readFields = () => (typeof fields === 'function' ? fields() : fields)
  const readForm = () => (typeof form === 'function' ? form() : form)

  /** Fields whose `when` condition currently holds. */
  const visibleFields = computed(() => {
    const values = readForm()
    return readFields().filter(
      (field) => field.when === undefined || evaluateCondition(field.when, values)
    )
  })

  /**
   * The form values minus anything currently hidden.
   *
   * Hiding a field must not destroy what the user typed — toggling it back on
   * should restore it — but the hidden value has no business being submitted.
   * So the model keeps everything and this is what gets sent.
   */
  const visibleData = computed(() => {
    const values = readForm()
    const keep = new Set(visibleFields.value.map((field) => field.key))

    return Object.fromEntries(
      Object.entries(values).filter(([key]) => keep.has(key))
    )
  })

  /**
   * Client-side rule failures, keyed by field.
   *
   * Only visible fields are checked — a hidden field is not part of the form
   * the user is filling in, so it must not be able to block a submit with a
   * message nobody can see or act on.
   */
  const clientErrors = computed(() => {
    const values = readForm()
    const errors: Record<string, string> = {}

    for (const field of visibleFields.value) {
      const message = firstError(field.rules, values[field.key], values)
      if (message !== null) {
        errors[field.key] = message
      }
    }

    return errors
  })

  const isValid = computed(() => Object.keys(clientErrors.value).length === 0)

  /**
   * Build the props object to spread onto a field component.
   * Maps FieldDef properties to the common field component API.
   */
  function fieldProps(field: FieldDef, errors: Record<string, string> = {}): Record<string, unknown> {
    const base: Record<string, unknown> = {
      label:       field.label,
      width:       field.width ?? 'full',
      required:    field.required
        ?? (field.requiredWhen !== undefined && evaluateCondition(field.requiredWhen, readForm())),
      error:       errors[field.key] ?? '',
      ...(field.help        ? { help: field.help }               : {}),
      ...(field.placeholder ? { placeholder: field.placeholder } : {}),
      ...(field.options     ? { options: field.options }         : {}),
      ...(field.fields      ? { fields: field.fields }           : {}),
      ...(field.props       ?? {}),
    }

    // Row-scoped errors for container fields, keyed relative to the field —
    // the server's `cast.2.actor` reaches a repeater as `2.actor`, so the
    // component can pin the message to its row without knowing its own key.
    const prefix = `${field.key}.`
    const nested: Record<string, string> = {}
    for (const [errorKey, message] of Object.entries(errors)) {
      if (errorKey.startsWith(prefix)) {
        nested[errorKey.slice(prefix.length)] = message
      }
    }
    if (Object.keys(nested).length > 0) {
      base.nestedErrors = nested
    }

    return base
  }

  return {
    visibleFields,
    visibleData,
    clientErrors,
    isValid,
    fieldProps,
    resolveFieldComponent,
  }
}

// ── Helper for defining blueprints with type inference ────────────────────────

/**
 * Type-safe helper for defining a blueprint field array.
 *
 * @example
 * const fields = defineBlueprint([
 *   { type: 'text', key: 'name', label: 'Name', width: '1/2' },
 * ])
 */
export function defineBlueprint(fields: FieldDef[]): FieldDef[] {
  return fields
}
