import { describe, it, expect } from 'vitest'
import { titleLabel, sentenceLabel } from '../src/Utils/labels'

describe('titleLabel', () => {
  it.each([
    ['events', 'Events'],
    ['purchase_orders', 'Purchase Orders'],
    ['finished-products', 'Finished Products'],
  ])('%s → %s', (input, expected) => {
    expect(titleLabel(input)).toBe(expected)
  })
})

describe('sentenceLabel', () => {
  it.each([
    ['events', 'Events'],
    ['postal_code', 'Postal code'],
    ['finished-products', 'Finished products'],
  ])('%s → %s', (input, expected) => {
    expect(sentenceLabel(input)).toBe(expected)
  })
})

describe('both registers', () => {
  // The old copies differed here: some collapsed separator runs, some did not.
  it.each([
    ['first__name', 'First Name', 'First name'],
    ['first-name ', 'First Name', 'First name'],
    ['', '', ''],
  ])('%s normalises to %s / %s', (input, title, sentence) => {
    expect(titleLabel(input)).toBe(title)
    expect(sentenceLabel(input)).toBe(sentence)
  })
})
