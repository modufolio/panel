import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { matchColorRule, type SchemaColumn } from '../src/Components/Table/tableSchema'
import SchemaTable from '../src/Components/Table/SchemaTable.vue'

const column = (colorRules: SchemaColumn['colorRules'], type = 'numeric'): SchemaColumn =>
  ({ key: 'balance', name: 'balance', label: 'Balance', type, colorRules }) as SchemaColumn

describe('matchColorRule', () => {
  const thresholds = column([
    { operator: 'lt', value: 0, color: 'danger' },
    { operator: 'gte', value: 1000, color: 'success' },
  ])

  it('takes the first rule that matches, in declared order', () => {
    expect(matchColorRule(thresholds, -1)?.color).toBe('danger')
    expect(matchColorRule(thresholds, 1000)?.color).toBe('success')
    expect(matchColorRule(thresholds, 5000)?.color).toBe('success')
  })

  it('leaves a value between the thresholds alone', () => {
    expect(matchColorRule(thresholds, 0)).toBeNull()
    expect(matchColorRule(thresholds, 999)).toBeNull()
  })

  it('compares numbers the server sent as strings', () => {
    // A JSON:API payload may carry a decimal as a string; "−1" is still below zero.
    expect(matchColorRule(thresholds, '-1')?.color).toBe('danger')
    expect(matchColorRule(thresholds, '1000')?.color).toBe('success')
  })

  it('does not call an absent value small', () => {
    // null is not "less than zero"; it is nothing, and colouring it red would
    // be a claim about data that does not exist.
    for (const empty of [null, undefined, '']) {
      expect(matchColorRule(thresholds, empty)).toBeNull()
    }
  })

  it('colours the empty case only when asked to', () => {
    const withEmpty = column([{ operator: 'empty', color: 'gray' }])

    expect(matchColorRule(withEmpty, null)?.color).toBe('gray')
    expect(matchColorRule(withEmpty, 0)).toBeNull()
  })

  it('understands between as both bounds included', () => {
    const band = column([{ operator: 'between', value: [10, 20], color: 'warning' }])

    expect(matchColorRule(band, 10)?.color).toBe('warning')
    expect(matchColorRule(band, 20)?.color).toBe('warning')
    expect(matchColorRule(band, 21)).toBeNull()
  })

  it('equals works for text as well as numbers', () => {
    const status = column([{ operator: 'equals', value: 'on_hold', color: 'warning' }], 'badge')

    expect(matchColorRule(status, 'on_hold')?.color).toBe('warning')
    expect(matchColorRule(status, 'active')).toBeNull()
  })

  it('refuses to compare what is not a number', () => {
    expect(matchColorRule(thresholds, 'not a number')).toBeNull()
  })

  it('ignores a rule with an operator it does not know', () => {
    expect(matchColorRule(column([{ operator: 'approximately', value: 3, color: 'danger' }]), 3)).toBeNull()
  })
})

describe('a table cell coloured by a rule', () => {
  function render(value: number) {
    return mount(SchemaTable, {
      props: {
        schema: {
          columns: [
            {
              key: 'issue_count',
              name: 'issue_count',
              label: 'Issues',
              type: 'numeric',
              sortable: false,
              colorRules: [
                { operator: 'gte', value: 10, color: 'danger' },
                { operator: 'gte', value: 5, color: 'warning' },
              ],
            },
          ],
        },
        records: [{ id: 1, issue_count: value }],
      },
    })
  }

  it('paints the value with the matching rule and leaves the rest plain', () => {
    expect(render(12).html()).toContain('text-danger')
    expect(render(6).html()).toContain('text-warning')

    const calm = render(1).html()
    expect(calm).not.toContain('text-danger')
    expect(calm).not.toContain('text-warning')
  })
})
