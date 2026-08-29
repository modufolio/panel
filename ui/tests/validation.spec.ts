import { describe, it, expect } from 'vitest'
import { ref } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import {
  required, min, max, email, url, pattern, integer, same, firstError,
  useBlueprint, defineBlueprint, BlueprintForm, rulesFromSpec, type FieldDef,
} from '../src/index'

const form = {}

describe('rules', () => {
  it('required rejects only genuinely blank values', () => {
    expect(required()(undefined, form)).not.toBe(true)
    expect(required()('', form)).not.toBe(true)
    expect(required()([], form)).not.toBe(true)
    // 0 and false are values, not absences.
    expect(required()(0, form)).toBe(true)
    expect(required()(false, form)).toBe(true)
  })

  it('every other rule passes on a blank value, leaving emptiness to required', () => {
    // Otherwise an optional field would complain about format for being untouched.
    expect(email()('', form)).toBe(true)
    expect(min(5)('', form)).toBe(true)
    expect(url()(null, form)).toBe(true)
    expect(integer()(undefined, form)).toBe(true)
  })

  it('min and max measure strings, numbers and arrays with one rule', () => {
    expect(min(3)('ab', form)).not.toBe(true)
    expect(min(3)('abc', form)).toBe(true)
    expect(min(3)(2, form)).not.toBe(true)
    expect(min(3)(3, form)).toBe(true)
    expect(min(2)(['a'], form)).not.toBe(true)
    expect(min(2)(['a', 'b'], form)).toBe(true)

    expect(max(2)('abc', form)).not.toBe(true)
    expect(max(2)(['a', 'b'], form)).toBe(true)
  })

  it('describes what it measured in the default message', () => {
    expect(String(min(3)('ab', form))).toContain('length')
    expect(String(min(3)(1, form))).toContain('value')
    expect(String(min(3)(['a'], form))).toContain('selection')
  })

  it('validates emails, urls, patterns and integers', () => {
    expect(email()('art@estherquelle.com', form)).toBe(true)
    expect(email()('not-an-email', form)).not.toBe(true)

    expect(url()('https://example.com/x', form)).toBe(true)
    expect(url()('example', form)).not.toBe(true)

    expect(pattern(/^[a-z-]+$/)('fine-art', form)).toBe(true)
    expect(pattern(/^[a-z-]+$/)('Fine Art', form)).not.toBe(true)

    expect(integer()('42', form)).toBe(true)
    expect(integer()('4.2', form)).not.toBe(true)
  })

  it('same compares against another field', () => {
    expect(same('password')('abc', { password: 'abc' })).toBe(true)
    expect(same('password')('abc', { password: 'xyz' })).not.toBe(true)
  })

  it('accepts a custom message', () => {
    expect(required('Give it a title.')(null, form)).toBe('Give it a title.')
  })

  it('firstError stops at the first failure', () => {
    expect(firstError([required(), min(5)], '', form)).toBe('This field is required.')
    expect(firstError([required(), min(5)], 'abc', form)).toContain('Minimum')
    expect(firstError([required(), min(5)], 'abcdef', form)).toBeNull()
    expect(firstError(undefined, '', form)).toBeNull()
  })
})

describe('useBlueprint validation', () => {
  const fields = defineBlueprint([
    { type: 'text', key: 'title', label: 'Title', rules: [required()] },
    { type: 'text', key: 'reason', label: 'Reason', when: ['status', 'rejected'], rules: [required()] },
  ] satisfies FieldDef[])

  it('reports failures per field and tracks overall validity', () => {
    const model = ref<Record<string, unknown>>({ status: 'pending', title: '', reason: '' })
    const { clientErrors, isValid } = useBlueprint(() => fields, () => model.value)

    expect(clientErrors.value).toHaveProperty('title')
    expect(isValid.value).toBe(false)

    model.value = { ...model.value, title: 'Set' }

    expect(clientErrors.value).toEqual({})
    expect(isValid.value).toBe(true)
  })

  it('ignores rules on hidden fields', () => {
    // A hidden required field must not block a submit with a message the user
    // cannot see or act on.
    const model = ref<Record<string, unknown>>({ status: 'pending', title: 'Set', reason: '' })
    const { clientErrors, isValid } = useBlueprint(() => fields, () => model.value)

    expect(isValid.value).toBe(true)

    model.value = { ...model.value, status: 'rejected' }

    expect(clientErrors.value).toHaveProperty('reason')
    expect(isValid.value).toBe(false)
  })
})

describe('BlueprintForm error display', () => {
  const fields = defineBlueprint([
    { type: 'text', key: 'title', label: 'Title', rules: [required('Title is required.')] },
  ] satisfies FieldDef[])

  it('stays quiet until the field is touched or a submit is attempted', async () => {
    const wrapper = mount(BlueprintForm, {
      props: { fields, modelValue: { title: '' } },
    })
    await flushPromises()

    expect(wrapper.text()).not.toContain('Title is required.')

    expect((wrapper.vm as unknown as { validate: () => boolean }).validate()).toBe(false)
    await flushPromises()

    expect(wrapper.text()).toContain('Title is required.')
  })

  it('shows a server error until that field is edited', async () => {
    const wrapper = mount(BlueprintForm, {
      props: { fields, modelValue: { title: 'Taken' }, errors: { title: 'Already in use.' } },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Already in use.')

    await wrapper.find('input').setValue('Something else')
    await flushPromises()

    // Editing the field retires the server's verdict — it was about the old value.
    expect(wrapper.text()).not.toContain('Already in use.')
  })
})

describe('rulesFromSpec', () => {
  it('builds rules from a server-declared spec', () => {
    const rules = rulesFromSpec({ required: true, max: 5 })

    expect(firstError(rules, '', form)).toContain('required')
    expect(firstError(rules, 'abcdef', form)).toContain('Maximum')
    expect(firstError(rules, 'abc', form)).toBeNull()
  })

  it('reports absence before format on an empty value', () => {
    const rules = rulesFromSpec({ required: true, email: true })

    expect(firstError(rules, '', form)).toContain('required')
  })

  it('converts a PCRE-delimited pattern, flags and all', () => {
    const rules = rulesFromSpec({ pattern: '/^[a-z-]+$/i' })

    expect(firstError(rules, 'Fine-Art', form)).toBeNull()
    expect(firstError(rules, 'fine art', form)).not.toBeNull()
  })

  it('ignores rule names it does not know rather than throwing', () => {
    // The server may know rules this client build does not.
    const rules = rulesFromSpec({ required: true, someFutureRule: 'x' })

    expect(firstError(rules, 'ok', form)).toBeNull()
  })

  it('skips an unparseable pattern instead of breaking the render', () => {
    const rules = rulesFromSpec({ pattern: '/[unclosed/' })

    expect(() => firstError(rules, 'anything', form)).not.toThrow()
  })

  it('returns nothing for an absent spec', () => {
    expect(rulesFromSpec(undefined)).toEqual([])
  })
})
