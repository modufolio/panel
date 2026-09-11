import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { ApiError, apiErrorMessage, errorMessages, flattenErrors, useFormErrors } from '../src/index'

describe('flattenErrors', () => {
  it('takes the first message of a field that failed more than one rule', () => {
    expect(flattenErrors({ email: ['Enter an email.', 'Too long.'] })).toEqual({
      email: 'Enter an email.',
    })
  })

  it('passes a plain string through, for anything still sending one', () => {
    expect(flattenErrors({ name: 'Required.' })).toEqual({ name: 'Required.' })
  })

  it('drops fields with nothing to say, so a control renders clean', () => {
    expect(flattenErrors({ a: [], b: '', c: null, d: undefined })).toEqual({})
  })

  it('survives a missing bag', () => {
    expect(flattenErrors(undefined)).toEqual({})
  })
})

describe('errorMessages', () => {
  it('flattens every message, in order', () => {
    expect(errorMessages({ a: ['one', 'two'], b: 'three' })).toEqual(['one', 'two', 'three'])
  })
})

describe('useFormErrors', () => {
  it('recomputes as the form\'s errors change', () => {
    const errors = ref<Record<string, string[]>>({ name: ['Required.'] })
    const form = { get errors() { return errors.value } }

    const flat = useFormErrors(form)
    expect(flat.value).toEqual({ name: 'Required.' })

    errors.value = {}
    expect(flat.value).toEqual({})
  })
})

describe('apiErrorMessage', () => {
  const rejected = (body: unknown) => new ApiError('Request failed', 422, body)

  it('prefers the validation bag, flattened', () => {
    expect(apiErrorMessage(rejected({ errors: { title: ['Required.'] } }), 'Nope')).toBe('Required.')
  })

  it('falls through to a single error string, then to message', () => {
    expect(apiErrorMessage(rejected({ error: 'Transition not allowed' }), 'Nope')).toBe('Transition not allowed')
    expect(apiErrorMessage(rejected({ message: 'Conflict' }), 'Nope')).toBe('Conflict')
  })

  it('uses the fallback for a body that says nothing useful', () => {
    expect(apiErrorMessage(rejected({ errors: {} }), 'Nope')).toBe('Nope')
    expect(apiErrorMessage(rejected(null), 'Nope')).toBe('Nope')
  })

  it('uses the fallback for anything that is not an ApiError', () => {
    expect(apiErrorMessage(new TypeError('Failed to fetch'), 'Nope')).toBe('Nope')
  })
})
