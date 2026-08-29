import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import {
  FilterIndicators,
  SchemaTable,
  TextInputColumn,
  registerColumnType,
  registeredColumnTypes,
  resolveColumnComponent,
  type TableSchema,
} from '../src/index'

describe('TextInputColumn', () => {
  const mountCell = (props: Record<string, unknown> = {}) =>
    mount(TextInputColumn, {
      props: { value: 'Butter', record: { id: 1 }, column: 'name', ...props },
    })

  it('shows the committed value', () => {
    expect((mountCell().find('input').element as HTMLInputElement).value).toBe('Butter')
  })

  it('saves on Enter through the update handler', async () => {
    const onUpdate = vi.fn().mockResolvedValue(undefined)
    const wrapper = mountCell({ onUpdate })

    await wrapper.find('input').setValue('Margarine')
    await wrapper.find('input').trigger('keydown.enter')
    await flushPromises()

    expect(onUpdate).toHaveBeenCalledWith({ id: 1 }, 'name', 'Margarine')
  })

  it('saves on blur', async () => {
    const onUpdate = vi.fn().mockResolvedValue(undefined)
    const wrapper = mountCell({ onUpdate })

    await wrapper.find('input').setValue('Margarine')
    await wrapper.find('input').trigger('blur')
    await flushPromises()

    expect(onUpdate).toHaveBeenCalledTimes(1)
    expect(onUpdate).toHaveBeenCalledWith({ id: 1 }, 'name', 'Margarine')
  })

  /**
   * Vue treats an `onUpdate` prop as a listener for a declared `update` emit.
   * Doing both called the page's save handler a second time with the event
   * object in place of (record, column, value) — which reached useInlineEdit
   * as a record without an id.
   */
  it('calls the update handler exactly once per edit', async () => {
    const onUpdate = vi.fn().mockResolvedValue(undefined)
    const wrapper = mountCell({ onUpdate })

    await wrapper.find('input').setValue('Margarine')
    await wrapper.find('input').trigger('keydown.enter')
    await flushPromises()

    expect(onUpdate).toHaveBeenCalledTimes(1)
    expect(wrapper.emitted('update')).toBeFalsy()
  })

  it('emits update for a parent that listens instead of passing a handler', async () => {
    const wrapper = mountCell()

    await wrapper.find('input').setValue('Margarine')
    await wrapper.find('input').trigger('keydown.enter')
    await flushPromises()

    expect(wrapper.emitted('update')?.[0]?.[0]).toMatchObject({ newValue: 'Margarine' })
  })

  /** Blur fires on every pass through the cell; an unchanged value is not an edit. */
  it('does not save when nothing changed', async () => {
    const onUpdate = vi.fn().mockResolvedValue(undefined)
    const wrapper = mountCell({ onUpdate })

    await wrapper.find('input').trigger('blur')
    await flushPromises()

    expect(onUpdate).not.toHaveBeenCalled()
  })

  it('reverts on Escape without saving', async () => {
    const onUpdate = vi.fn().mockResolvedValue(undefined)
    const wrapper = mountCell({ onUpdate })

    await wrapper.find('input').setValue('Margarine')
    await wrapper.find('input').trigger('keydown.esc')
    await flushPromises()

    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Butter')
    expect(onUpdate).not.toHaveBeenCalled()
  })

  /** Retyping a rejected edit from memory is worse than seeing it marked unsaved. */
  it('keeps the typed value and reports the failure when the save is rejected', async () => {
    const onUpdate = vi.fn().mockRejectedValue(new Error('Name is taken'))
    const wrapper = mountCell({ onUpdate })

    await wrapper.find('input').setValue('Margarine')
    await wrapper.find('input').trigger('keydown.enter')
    await flushPromises()

    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Margarine')
    expect(wrapper.find('[role="alert"]').text()).toBe('Name is taken')
    expect(wrapper.find('input').attributes('aria-invalid')).toBe('true')
  })

  it('does nothing while disabled', async () => {
    const onUpdate = vi.fn().mockResolvedValue(undefined)
    const wrapper = mountCell({ onUpdate, disabled: true })

    await wrapper.find('input').setValue('Margarine')
    await wrapper.find('input').trigger('keydown.enter')
    await flushPromises()

    expect(onUpdate).not.toHaveBeenCalled()
  })

  it('takes an external value change while the cell is not being edited', async () => {
    const wrapper = mountCell()

    await wrapper.setProps({ value: 'Reconciled' })

    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Reconciled')
  })
})

describe('registerColumnType', () => {
  const Sparkline = defineComponent({
    props: { value: { type: null, default: null }, record: { type: Object, required: true } },
    setup: (props) => () => h('span', { class: 'sparkline' }, `${props.record.id}:${props.value}`),
  })

  const schema: TableSchema = {
    columns: [{ key: 'trend', label: 'Trend', type: 'sparkline' as any }],
  } as TableSchema

  const records = [{ id: 7, trend: '1,2,3' }]

  it('renders a registered type through the schema table', () => {
    registerColumnType('sparkline', Sparkline)

    const wrapper = mount(SchemaTable, { props: { schema, records, filterValues: {} } as any })

    expect(wrapper.find('.sparkline').text()).toBe('7:1,2,3')
  })

  it('lists what has been registered and resolves it', () => {
    registerColumnType('sparkline', Sparkline)

    expect(registeredColumnTypes()).toContain('sparkline')
    expect(resolveColumnComponent('sparkline')).toBe(Sparkline)
    expect(resolveColumnComponent('text')).toBeUndefined()
  })

  /** Registering a built-in name replaces it — the same seam fields have. */
  it('overrides a built-in type', () => {
    const Loud = defineComponent({
      props: { label: { type: String, default: '' } },
      setup: (props) => () => h('span', { class: 'loud' }, props.label.toUpperCase()),
    })
    registerColumnType('badge', Loud)

    const wrapper = mount(SchemaTable, {
      props: {
        schema: { columns: [{ key: 'status', label: 'Status', type: 'badge' }] } as TableSchema,
        records: [{ id: 1, status: 'active' }],
        filterValues: {},
      } as any,
    })

    expect(wrapper.find('.loud').text()).toBe('ACTIVE')
  })
})

describe('FilterIndicators', () => {
  const indicators = [
    { key: 'status', label: 'Status', value: 'Active' },
    { key: 'industry', label: 'Industry', value: 'Retail' },
  ]

  it('names each active filter', () => {
    const wrapper = mount(FilterIndicators, { props: { indicators } })

    expect(wrapper.text()).toContain('Status')
    expect(wrapper.text()).toContain('Active')
    expect(wrapper.text()).toContain('Industry')
  })

  it('renders nothing when no filter is active', () => {
    expect(mount(FilterIndicators, { props: { indicators: [] } }).find('.ui-filter-indicators').exists()).toBe(false)
  })

  it('reports which chip was dismissed', async () => {
    const wrapper = mount(FilterIndicators, { props: { indicators } })

    await wrapper.findAll('button')[0].trigger('click')

    expect(wrapper.emitted('remove')?.[0]).toEqual(['status'])
  })

  it('offers Clear all only when more than one filter is on', () => {
    const one = mount(FilterIndicators, { props: { indicators: [indicators[0]] } })
    expect(one.text()).not.toContain('Clear all')

    const many = mount(FilterIndicators, { props: { indicators } })
    expect(many.text()).toContain('Clear all')
  })

  it('truncates a long value but keeps it whole in the title', () => {
    const long = 'a'.repeat(80)
    const wrapper = mount(FilterIndicators, {
      props: { indicators: [{ key: 'q', label: 'Query', value: long }] },
    })

    expect(wrapper.find(`[title="${long}"]`).text()).toHaveLength(40)
  })
})

describe('SchemaTable filter indicators', () => {
  const schema = {
    columns: [{ key: 'name', label: 'Name', type: 'text' }],
    filters: [
      { key: 'status', type: 'select', label: 'Status', options: [{ label: 'Active', value: 'active' }] },
      { key: 'is_company', type: 'ternary', label: 'Company', trueLabel: 'Companies', falseLabel: 'People' },
    ],
  } as unknown as TableSchema

  const mountTable = (filterValues: Record<string, unknown>) =>
    mount(SchemaTable, { props: { schema, records: [], filterValues } as any })

  it('shows nothing when no filter is set', () => {
    expect(mountTable({}).findComponent(FilterIndicators).props('indicators')).toEqual([])
  })

  /** A chip says "Active", not the `active` that travelled in the query string. */
  it('describes a select filter with its option label', () => {
    const chips = mountTable({ status: 'active' }).findComponent(FilterIndicators).props('indicators')

    expect(chips).toEqual([{ key: 'status', label: 'Status', value: 'Active' }])
  })

  it('describes a ternary filter with its own wording', () => {
    const chips = mountTable({ is_company: '1' }).findComponent(FilterIndicators).props('indicators')

    expect(chips?.[0]?.value).toBe('Companies')
  })

  it('empties just the filter whose chip was dismissed', async () => {
    const wrapper = mountTable({ status: 'active', is_company: '1' })

    wrapper.findComponent(FilterIndicators).vm.$emit('remove', 'status')
    await flushPromises()

    expect(wrapper.emitted('update:filter')?.[0]).toEqual(['status', ''])
  })
})
