import { describe, it, expect } from 'vitest'
import { reconcileColumnPreferences, columnPreferencesFor } from '../src/Composables/columnPreferences'
import type { TableSchema } from '../src/Components/Table/tableSchema'

const schema = {
  columns: [
    { key: 'title', name: 'title', label: 'Title', type: 'text', toggleable: false },
    { key: 'year', name: 'year', label: 'Year', type: 'text' },
    { key: 'rating', name: 'rating', label: 'Rating', type: 'text', hiddenByDefault: true },
    { key: 'studio', name: 'studio', label: 'Studio', type: 'text' },
  ],
} as unknown as TableSchema

/**
 * A remembered column set is reconciled against the schema on every load:
 * forgotten columns drop, new ones start as declared, and what cannot be
 * toggled is always shown.
 */
describe('column preferences', () => {
  it('starts from the schema when nothing is remembered', () => {
    expect(reconcileColumnPreferences(null, schema)).toEqual(['title', 'year', 'studio'])
  })

  it('applies what was remembered, and only that', () => {
    const visible = reconcileColumnPreferences([{ name: 'year', hidden: true }, { name: 'rating', hidden: false }], schema)

    expect(visible).toEqual(['title', 'rating', 'studio'])
  })

  it('forgets a column the resource no longer has and shows a new one as declared', () => {
    const visible = reconcileColumnPreferences([{ name: 'genre', hidden: true }, { name: 'studio', hidden: true }], schema)

    expect(visible).toEqual(['title', 'year'])
  })

  it('never hides a column that is not toggleable, whatever was remembered', () => {
    expect(reconcileColumnPreferences([{ name: 'title', hidden: true }], schema)).toContain('title')
  })

  it('ignores a corrupt entry rather than failing the page', () => {
    expect(reconcileColumnPreferences([{ name: 42, hidden: 'yes' } as never, null as never], schema)).toEqual(['title', 'year', 'studio'])
  })

  it('remembers only what the viewer may toggle', () => {
    expect(columnPreferencesFor(['title', 'rating'], schema)).toEqual([
      { name: 'year', hidden: true },
      { name: 'rating', hidden: false },
      { name: 'studio', hidden: true },
    ])
  })
})
