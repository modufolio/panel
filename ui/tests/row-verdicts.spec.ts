import { describe, it, expect } from 'vitest'
import { visibleRowActions, type SchemaRowAction } from '../src/Components/Table/tableSchema'
import { fillId } from '../src/Composables/useResourceListing'

const actions: SchemaRowAction[] = [
  { name: 'view', behaviour: 'drawer', label: 'View' },
  { name: 'edit', behaviour: 'visit', label: 'Edit', urlTemplate: '/panel/users/{id}/edit' },
  { name: 'delete', behaviour: 'delete', label: 'Delete', urlTemplate: '/panel/users/{id}' },
  { name: 'restore', behaviour: 'post', label: 'Restore' },
  { name: 'archive', behaviour: 'handler', label: 'Archive' },
]

const record = { id: 7, name: 'Ada' }

describe('per-record verdicts on row actions', () => {
  it('offers everything the schema declares when the server sent no verdict', () => {
    expect(visibleRowActions(actions, record).map((a) => a.name)).toEqual([
      'view', 'edit', 'delete', 'restore', 'archive',
    ])
  })

  it('hides edit when the record may not be edited', () => {
    const names = visibleRowActions(actions, record, { edit: false, delete: true }).map((a) => a.name)

    expect(names).toEqual(['view', 'delete', 'restore', 'archive'])
  })

  it('hides delete and restore together: both govern the trash lifecycle', () => {
    const names = visibleRowActions(actions, record, { edit: true, delete: false }).map((a) => a.name)

    expect(names).toEqual(['view', 'edit', 'archive'])
  })

  it('leaves actions that are not a verdict\'s business alone', () => {
    const names = visibleRowActions(actions, record, { edit: false, delete: false }).map((a) => a.name)

    expect(names).toEqual(['view', 'archive'])
  })

  it('keeps a refused action on the menu, disabled, when the server said why', () => {
    const shown = visibleRowActions(actions, record, { edit: true, delete: false }, { delete: 'Admins cannot be deleted' })

    expect(shown.map((a) => a.name)).toEqual(['view', 'edit', 'delete', 'restore', 'archive'])
    expect(shown.find((a) => a.name === 'delete')).toMatchObject({ disabled: true, disabledReason: 'Admins cannot be deleted' })
    expect(shown.find((a) => a.name === 'restore')).toMatchObject({ disabled: true })
    expect(shown.find((a) => a.name === 'edit')?.disabled).toBeUndefined()
  })

  it('still honours the schema\'s own visibility fields alongside a verdict', () => {
    const gated: SchemaRowAction[] = [
      { name: 'edit', behaviour: 'visit', label: 'Edit', hiddenWhen: 'locked' },
    ]

    expect(visibleRowActions(gated, { id: 1, locked: true }, { edit: true, delete: true })).toEqual([])
    expect(visibleRowActions(gated, { id: 1, locked: false }, { edit: true, delete: true })).toHaveLength(1)
  })
})

describe('fillId', () => {
  it('substitutes the id into a server template', () => {
    expect(fillId('/panel/users/{id}/edit', 'a1b2')).toBe('/panel/users/a1b2/edit')
  })

  it('is null for a route the resource did not generate', () => {
    expect(fillId(null, 1)).toBeNull()
    expect(fillId(undefined, 1)).toBeNull()
  })

  it('encodes an id that would otherwise break the path', () => {
    expect(fillId('/panel/users/{id}', 'a/b c')).toBe('/panel/users/a%2Fb%20c')
  })
})
