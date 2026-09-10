import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { QueryBuilder } from '../src/index'
import type { SchemaConstraint, QueryCondition } from '../src/index'

/**
 * The query builder composes the conditions the schema declared as
 * `constraints`. Everything it knows comes from that list — which fields can
 * be filtered, which operators each accepts, and how many values an operator
 * takes — so the tests below drive it entirely through one.
 */
const constraints: SchemaConstraint[] = [
  {
    key: 'title',
    type: 'text',
    label: 'Title',
    operators: [
      { value: 'contains', label: 'contains', values: 1 },
      { value: 'empty', label: 'is empty', values: 0 },
    ],
  },
  {
    key: 'seats',
    type: 'number',
    label: 'Seats',
    operators: [
      { value: 'eq', label: '=', values: 1 },
      { value: 'between', label: 'between', values: 2 },
    ],
  },
  {
    key: 'published',
    type: 'boolean',
    label: 'Published',
    operators: [{ value: 'is', label: 'is', values: 1 }],
  },
  {
    key: 'startsOn',
    type: 'date',
    label: 'Starts on',
    operators: [{ value: 'after', label: 'after', values: 1 }],
  },
]

function builder(modelValue: QueryCondition[] = []) {
  return mount(QueryBuilder, { props: { constraints, modelValue } })
}

/** The "add condition" button, found by its words rather than its styling. */
function addButton(wrapper: ReturnType<typeof builder>) {
  const button = wrapper.findAll('button').find((b) => b.text().includes('Add condition'))
  expect(button, 'expected an add-condition button').toBeTruthy()
  return button!
}

/** The single payload a v-model emission carried. */
function emitted(wrapper: ReturnType<typeof builder>): QueryCondition[] {
  const events = wrapper.emitted('update:modelValue')
  expect(events, 'expected the builder to emit').toBeTruthy()
  return events!.at(-1)![0] as QueryCondition[]
}

describe('QueryBuilder', () => {
  it('says so when there is nothing to show, rather than rendering an empty frame', () => {
    expect(builder().text()).toContain('No conditions yet.')
  })

  it('adds a condition on the first constraint, with its first operator', async () => {
    const wrapper = builder()

    await addButton(wrapper).trigger('click')

    expect(emitted(wrapper)).toEqual([{ key: 'title', operator: 'contains', value: '' }])
  })

  it('adds nothing when the schema declared no constraints', async () => {
    const wrapper = mount(QueryBuilder, { props: { constraints: [], modelValue: [] } })

    await addButton(wrapper).trigger('click')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('appends rather than replaces, so a second condition keeps the first', async () => {
    const wrapper = builder([{ key: 'seats', operator: 'eq', value: '3' }])

    await addButton(wrapper).trigger('click')

    expect(emitted(wrapper)).toEqual([
      { key: 'seats', operator: 'eq', value: '3' },
      { key: 'title', operator: 'contains', value: '' },
    ])
  })

  it('reads the first row as "Where" and every later one as "And"', () => {
    const wrapper = builder([
      { key: 'title', operator: 'contains', value: 'a' },
      { key: 'seats', operator: 'eq', value: '2' },
    ])

    const rows = wrapper.findAll('.ui-query-builder > div')
    expect(rows[0]!.text()).toContain('Where')
    expect(rows[1]!.text()).toContain('And')
  })

  it('offers only the operators the chosen field declares', () => {
    const wrapper = builder([{ key: 'published', operator: 'is', value: '1' }])

    const operators = wrapper
      .findAll('select')[1]!
      .findAll('option')
      .map((option) => option.attributes('value'))

    expect(operators).toEqual(['is'])
  })

  describe('value inputs follow the operator arity', () => {
    it('draws none for a nullary operator', () => {
      const wrapper = builder([{ key: 'title', operator: 'empty' }])

      expect(wrapper.find('[aria-label="Condition 1 value"]').exists()).toBe(false)
    })

    it('draws one for a unary operator', () => {
      const wrapper = builder([{ key: 'title', operator: 'contains', value: 'jaws' }])

      expect(wrapper.find('[aria-label="Condition 1 value"]').exists()).toBe(true)
      expect(wrapper.find('[aria-label="Condition 1 second value"]').exists()).toBe(false)
    })

    it('draws two, joined by "and", for a binary operator', () => {
      const wrapper = builder([{ key: 'seats', operator: 'between', value: '1', value2: '9' }])

      expect(wrapper.find('[aria-label="Condition 1 second value"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('and')
    })

    it('assumes one value when the operator is not in the list', () => {
      const wrapper = builder([{ key: 'title', operator: 'gone', value: 'x' }])

      expect(wrapper.find('[aria-label="Condition 1 value"]').exists()).toBe(true)
    })
  })

  describe('the input matches the field type', () => {
    it.each([
      ['title', 'text'],
      ['seats', 'number'],
      ['startsOn', 'date'],
    ])('%s takes a %s input', (key, type) => {
      const operator = constraints.find((c) => c.key === key)!.operators[0]!.value
      const wrapper = builder([{ key, operator }])

      expect(wrapper.find('[aria-label="Condition 1 value"]').attributes('type')).toBe(type)
    })

    it('a boolean is two choices rather than free text', () => {
      const wrapper = builder([{ key: 'published', operator: 'is', value: '0' }])
      const control = wrapper.find('[aria-label="Condition 1 value"]')

      expect(control.element.tagName).toBe('SELECT')
      expect(control.findAll('option').map((o) => o.text())).toEqual(['Yes', 'No'])
      expect((control.element as HTMLSelectElement).value).toBe('0')
    })

    it("a boolean with no value yet shows the Yes it will be saved as", () => {
      const wrapper = builder([{ key: 'published', operator: 'is' }])

      expect((wrapper.find('[aria-label="Condition 1 value"]').element as HTMLSelectElement).value).toBe('1')
    })
  })

  it('gives a fresh boolean condition the value its select already shows', async () => {
    const wrapper = mount(QueryBuilder, {
      props: { constraints: [constraints[2]!], modelValue: [] },
    })

    await addButton(wrapper).trigger('click')

    expect(emitted(wrapper)).toEqual([{ key: 'published', operator: 'is', value: '1' }])
  })

  it('edits one condition and leaves its neighbours alone', async () => {
    const wrapper = builder([
      { key: 'title', operator: 'contains', value: 'old' },
      { key: 'seats', operator: 'eq', value: '4' },
    ])

    await wrapper.findAll('[aria-label="Condition 1 value"]')[0]!.setValue('new')

    expect(emitted(wrapper)).toEqual([
      { key: 'title', operator: 'contains', value: 'new' },
      { key: 'seats', operator: 'eq', value: '4' },
    ])
  })

  it('writes the second value of a range separately from the first', async () => {
    const wrapper = builder([{ key: 'seats', operator: 'between', value: '1', value2: '' }])

    await wrapper.find('[aria-label="Condition 1 second value"]').setValue('9')

    expect(emitted(wrapper)).toEqual([{ key: 'seats', operator: 'between', value: '1', value2: '9' }])
  })

  describe('switching field', () => {
    it('resets the operator, which the new type would not accept', async () => {
      const wrapper = builder([{ key: 'title', operator: 'contains', value: 'jaws' }])

      await wrapper.findAll('select')[0]!.setValue('seats')

      expect(emitted(wrapper)).toEqual([{ key: 'seats', operator: 'eq', value: '', value2: '' }])
    })

    it('clears both values, so a range does not leave a second one behind', async () => {
      const wrapper = builder([{ key: 'seats', operator: 'between', value: '1', value2: '9' }])

      await wrapper.findAll('select')[0]!.setValue('title')

      expect(emitted(wrapper)).toEqual([{ key: 'title', operator: 'contains', value: '', value2: '' }])
    })

    it('seeds a boolean with Yes rather than the empty text default', async () => {
      const wrapper = builder([{ key: 'title', operator: 'contains', value: 'x' }])

      await wrapper.findAll('select')[0]!.setValue('published')

      expect(emitted(wrapper)).toEqual([{ key: 'published', operator: 'is', value: '1', value2: '' }])
    })
  })

  it('removes the condition whose button was pressed, by position', async () => {
    const wrapper = builder([
      { key: 'title', operator: 'contains', value: 'a' },
      { key: 'seats', operator: 'eq', value: '2' },
      { key: 'startsOn', operator: 'after', value: '2026-01-01' },
    ])

    await wrapper.find('[aria-label="Remove condition 2"]').trigger('click')

    expect(emitted(wrapper)).toEqual([
      { key: 'title', operator: 'contains', value: 'a' },
      { key: 'startsOn', operator: 'after', value: '2026-01-01' },
    ])
  })

  it('never mutates the array it was handed', async () => {
    const modelValue: QueryCondition[] = [{ key: 'title', operator: 'contains', value: 'a' }]
    const wrapper = builder(modelValue)

    await addButton(wrapper).trigger('click')
    await wrapper.find('[aria-label="Remove condition 1"]').trigger('click')

    expect(modelValue).toEqual([{ key: 'title', operator: 'contains', value: 'a' }])
  })
})
