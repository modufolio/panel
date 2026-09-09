import { computed, ref, type ComputedRef, type Ref } from 'vue'
import { firstError, rulesFromSpec } from './validation'

/** A field spec as the PHP blueprints serialise it. */
export interface FieldRuleSpec {
  key: string
  label?: string
  rules?: Record<string, unknown>
}

/**
 * Client-side checking of rules a PHP blueprint declared.
 *
 * The server validates the same specs and is the authority; running them here
 * as well only spares a round trip and — more usefully — keeps what the user
 * typed on screen, since a rejected save redirects and would otherwise replace
 * their input with the stored value.
 *
 * Messages stay quiet until a field is edited or a save is attempted, so an
 * untouched form does not greet the user with complaints.
 */
export function useFieldRules(
  specs: () => FieldRuleSpec[],
  values: () => Record<string, unknown>,
  serverErrors: () => Record<string, string>,
) {
  const touched = ref(new Set<string>())
  const attempted = ref(false)

  const clientErrors: ComputedRef<Record<string, string>> = computed(() => {
    const current = values()
    const errors: Record<string, string> = {}

    for (const spec of specs()) {
      const message = firstError(rulesFromSpec(spec.rules), current[spec.key], current)
      if (message !== null) {
        errors[spec.key] = message
      }
    }

    return errors
  })

  const isValid = computed(() => Object.keys(clientErrors.value).length === 0)

  /** The message to show for a field, or undefined. */
  function errorFor(key: string): string | undefined {
    const client = clientErrors.value[key]

    if (client !== undefined && (attempted.value || touched.value.has(key))) {
      return client
    }

    // A server message stands until the user edits that field — it was about
    // the value they submitted, not the one they are typing now.
    return touched.value.has(key) ? undefined : serverErrors()[key]
  }

  function markTouched(key: string): void {
    touched.value = new Set(touched.value).add(key)
  }

  /** Reveal every outstanding message; returns whether the form may be sent. */
  function attempt(): boolean {
    attempted.value = true
    return isValid.value
  }

  return { clientErrors, isValid, errorFor, markTouched, attempt, touched: touched as Ref<Set<string>> }
}
