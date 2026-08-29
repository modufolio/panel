import { afterEach, describe, expect, it } from 'vitest'
import { moduleSingleton } from '../src/Utils/moduleSingleton'

const KEY = 'test-singleton'
const symbol = Symbol.for(`modufolio-panel.${KEY}`)

describe('moduleSingleton', () => {
  afterEach(() => {
    delete (globalThis as Record<symbol, unknown>)[symbol]
  })

  it('returns the same object for the same key — the duplicated-module case', () => {
    // Two calls stand in for two instantiations of one module.
    const first = moduleSingleton(KEY, () => ({ items: [] as number[] }))
    const second = moduleSingleton(KEY, () => ({ items: [] as number[] }))

    first.items.push(1)

    expect(second).toBe(first)
    expect(second.items).toEqual([1])
  })

  it('runs create exactly once', () => {
    let created = 0

    moduleSingleton(KEY, () => ++created)
    moduleSingleton(KEY, () => ++created)

    expect(created).toBe(1)
  })

  it('treats a falsy value as initialised', () => {
    expect(moduleSingleton(KEY, () => 0)).toBe(0)
    // A truthiness check would re-run create here and return 1.
    expect(moduleSingleton(KEY, () => 1)).toBe(0)
  })
})
