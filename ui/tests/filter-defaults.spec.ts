import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import {
  FilterIndicators,
  SchemaTable,
  defaultFilterValue,
  filterDefaults,
  isFilterDefault,
  type TableSchema,
} from '../src/index'

/**
 * A filter's default is what the server applies when the request names
 * none. Showing it is not filtering: it earns no chip and no badge count, and
 * a reset returns to it — otherwise a resource listing deleted rows by default
 * showed "With Deleted" as an applied filter that came straight back after
 * every reset.
 */
const trashed = {
  key: 'trashed',
  type: 'trashed',
  label: 'Deleted Users',
  trueLabel: 'With Deleted',
  falseLabel: 'Only Deleted',
  trueValue: 'with',
  falseValue: 'only',
  default: 'with',
}

const schema = {
  columns: [{ key: 'name', name: 'name', label: 'Name', type: 'text', sortable: false }],
  filters: [trashed, { key: 'role', type: 'select', label: 'Role', options: [{ label: 'Admin', value: 'admin' }] }],
} as unknown as TableSchema

describe('filter defaults', () => {
  it('resolve to the declared default, else the empty value', () => {
    expect(defaultFilterValue(schema.filters![0]!)).toBe('with')
    expect(defaultFilterValue(schema.filters![1]!)).toBe('')
    expect(filterDefaults(schema)).toEqual({ trashed: 'with', role: '' })
  })

  it('recognise a value equal to the default', () => {
    expect(isFilterDefault(schema.filters![0]!, 'with')).toBe(true)
    expect(isFilterDefault(schema.filters![0]!, 'only')).toBe(false)
    expect(isFilterDefault(schema.filters![1]!, 'admin')).toBe(false)
  })

  it('are neither counted nor chipped, while a real choice is', () => {
    const showingDefault = mount(SchemaTable, {
      props: { schema, records: [], filterValues: { trashed: 'with', role: '' } },
    })
    expect(showingDefault.findComponent(FilterIndicators).props('indicators')).toEqual([])

    const onlyDeleted = mount(SchemaTable, {
      props: { schema, records: [], filterValues: { trashed: 'only', role: '' } },
    })
    expect(onlyDeleted.findComponent(FilterIndicators).props('indicators')).toEqual([
      { key: 'trashed', label: 'Deleted Users', value: 'Only Deleted' },
    ])
  })
})
