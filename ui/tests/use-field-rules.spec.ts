import { describe, it, expect } from 'vitest'
import { reactive } from 'vue'
import { useFieldRules, type FieldRuleSpec } from '../src/Components/Fields/useFieldRules'

const specs: FieldRuleSpec[] = [
  { key: 'title', label: 'Title', rules: { required: true } },
  { key: 'email', label: 'Email', rules: { email: true } },
]

/**
 * The client mirror of the rules a PHP blueprint declared. The server is the
 * authority; this only spares a round trip and keeps what the user typed on
 * screen — so what matters is *when* a message appears, not that it exists.
 */
describe('useFieldRules', () => {
  it('says nothing about an untouched field', () => {
    const values = reactive({ title: '', email: '' })
    const rules = useFieldRules(() => specs, () => values, () => ({}))

    expect(rules.isValid.value).toBe(false)
    expect(rules.errorFor('title')).toBeUndefined()
  })

  it('shows a field’s message once it is touched, and clears it when fixed', () => {
    const values = reactive({ title: '', email: '' })
    const rules = useFieldRules(() => specs, () => values, () => ({}))

    rules.markTouched('title')
    expect(rules.errorFor('title')).toBeTruthy()
    // Touching one field says nothing about the others.
    expect(rules.errorFor('email')).toBeUndefined()

    values.title = 'A post'
    expect(rules.errorFor('title')).toBeUndefined()
  })

  it('attempt() reveals every outstanding message and refuses the send', () => {
    const values = reactive({ title: '', email: 'not-an-email' })
    const rules = useFieldRules(() => specs, () => values, () => ({}))

    expect(rules.attempt()).toBe(false)
    expect(rules.errorFor('title')).toBeTruthy()
    expect(rules.errorFor('email')).toBeTruthy()

    values.title = 'A post'
    values.email = 'ada@example.com'
    expect(rules.attempt()).toBe(true)
    expect(rules.errorFor('email')).toBeUndefined()
  })

  it('a server message stands until the user edits that field', () => {
    const values = reactive({ title: 'A post', email: 'ada@example.com' })
    const rules = useFieldRules(() => specs, () => values, () => ({ title: 'Already taken' }))

    expect(rules.errorFor('title')).toBe('Already taken')

    // It was about the value that was submitted, not the one being typed now.
    rules.markTouched('title')
    expect(rules.errorFor('title')).toBeUndefined()
  })
})
