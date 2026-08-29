import { describe, it, expect } from 'vitest'
import { reconcile } from '../src/index'

describe('reconcile', () => {
  it('updates scalar fields in place, preserving object identity', () => {
    const target = { id: 1, title: 'old', rating: 3 }
    const result = reconcile(target, { id: 1, title: 'new', rating: 5 })
    expect(result).toBe(target) // same object reference
    expect(target).toEqual({ id: 1, title: 'new', rating: 5 })
  })

  it('adds new keys and removes absent ones', () => {
    const target: Record<string, unknown> = { a: 1, b: 2 }
    reconcile(target, { a: 1, c: 3 })
    expect(target).toEqual({ a: 1, c: 3 })
  })

  it('keeps identity of matched array rows and only replaces new ones', () => {
    const row1 = { id: 1, name: 'a' }
    const row2 = { id: 2, name: 'b' }
    const target = [row1, row2]
    reconcile(target, [
      { id: 1, name: 'a-updated' },
      { id: 3, name: 'c' },
    ])
    expect(target[0]).toBe(row1) // identity preserved
    expect(target[0].name).toBe('a-updated') // updated in place
    expect(target[1]).toEqual({ id: 3, name: 'c' }) // new row
    expect(target).toHaveLength(2)
  })

  it('preserves the array reference itself', () => {
    const target = [{ id: 1 }]
    const result = reconcile(target, [{ id: 1 }, { id: 2 }])
    expect(result).toBe(target)
    expect(target).toHaveLength(2)
  })

  it('reconciles nested objects and arrays deeply', () => {
    const child = { id: 10, tags: [{ id: 1, label: 'x' }] }
    const target = { id: 1, child }
    reconcile(target, { id: 1, child: { id: 10, tags: [{ id: 1, label: 'y' }, { id: 2, label: 'z' }] } })
    expect(target.child).toBe(child) // nested identity preserved
    expect(target.child.tags[0].label).toBe('y')
    expect(target.child.tags).toHaveLength(2)
  })

  it('reconciles keyless arrays positionally', () => {
    const target = [1, 2, 3]
    reconcile(target, [9, 8])
    expect(target).toEqual([9, 8])
  })
})
