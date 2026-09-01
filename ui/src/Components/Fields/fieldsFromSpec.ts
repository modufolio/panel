import { rulesFromSpec } from './validation'
import type { FieldDef } from './useBlueprint'

/**
 * A field as `PanelResource::formFields()` serializes it: a {@link FieldDef}
 * whose `rules` are still the PHP map, plus the starting value it declares.
 */
export interface FieldSpec extends Omit<FieldDef, 'rules'> {
  rules?: Record<string, unknown>
  default?: unknown
}

/**
 * Turn a server field declaration into one the blueprint components can use.
 *
 * Fields arrive exactly as `PanelResource::formFields()` declared them, with
 * `rules` as the PHP map (`{max: 120}`). The blueprint layer expects rule
 * *functions*, so anything handed straight through fails the moment a field is
 * validated — `for (const rule of rules ?? [])` cannot iterate an object, and
 * `?? []` does not catch it because the value is not nullish.
 *
 * That is why this lives here rather than inside a page: the generated form and
 * the drawer's add form both render server declarations, and a second copy of
 * the conversion is a second chance for the two to disagree.
 */

/**
 * Mirror of the server's `coerceValues()`: a number input emits strings, but
 * min/max measure a string's *length* — "2004" would fail `min: 1888` as four
 * characters. Coerce before the rules run, exactly as the server does before
 * FieldValidator runs, so the two sides can never disagree.
 */
const coerceNumber = (value: unknown): unknown =>
  value === '' || value == null ? null : (Number.isNaN(Number(value)) ? value : Number(value))

/** Fields whose components iterate their model and so cannot be given null. */
const COLLECTION_TYPES = new Set(['repeater', 'multiselect'])

export function fieldsFromSpec(fields: FieldSpec[]): FieldDef[] {
  return (fields ?? []).map((field) => {
    const rules = field.rules ? rulesFromSpec(field.rules) : undefined
    const numeric = field.props?.type === 'number'

    return {
      ...field,
      rules: numeric && rules
        ? rules.map((rule) => (value: unknown, form: Record<string, unknown>) => rule(coerceNumber(value), form))
        : rules,
    }
  })
}

/**
 * Starting values for a set of declared fields.
 *
 * Collection-valued fields start as `[]`: their components iterate the model
 * (`[...modelValue]`, `v-for`), and an explicit null overrides a prop default,
 * so null does not degrade gracefully — it throws.
 */
export function initialValues(
  fields: Array<Pick<FieldSpec, 'key' | 'type' | 'default'>>,
  record: Record<string, unknown> | null = null,
): Record<string, unknown> {
  const initial: Record<string, unknown> = {}

  for (const field of fields ?? []) {
    const fallback = COLLECTION_TYPES.has(field.type as string) ? [] : null
    initial[field.key] = record?.[field.key] ?? field.default ?? fallback
  }

  return initial
}
