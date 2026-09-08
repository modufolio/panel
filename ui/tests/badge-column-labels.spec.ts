import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SchemaTable from '../src/Components/Table/SchemaTable.vue'

type TableSchema = import('../src/Components/Table/tableSchema').TableSchema

/**
 * A badge shows the case, not the column value it is stored under: the server
 * sends `on_hold` and the options say that case is called "On Hold". Reading
 * the raw value made the table disagree with the select beside it.
 */
describe('A badge column with options', () => {
  function render(column: Record<string, unknown>) {
    return mount(SchemaTable, {
      props: {
        schema: { columns: [column] } as unknown as TableSchema,
        records: [{ id: 1, status: 'on_hold' }],
        filterValues: {},
      } as never,
    })
  }

  it('renders the option label and its colour', () => {
    const wrapper = render({
      key: 'status',
      label: 'Status',
      type: 'badge',
      options: [{ value: 'on_hold', label: 'On Hold' }],
      colors: { on_hold: 'warning' },
    })

    expect(wrapper.text()).toContain('On Hold')
    expect(wrapper.find('.ui-badge').classes().join(' ')).toContain('warning')
  })

  /**
   * An enum written in plain hues — what `HasColorInterface::getColor()`
   * tends to return — lands on the matching semantic token instead of the
   * grey every unrecognised colour used to fall back to.
   */
  it('translates a plain hue into the semantic token', () => {
    const wrapper = render({
      key: 'status',
      label: 'Status',
      type: 'badge',
      options: [{ value: 'on_hold', label: 'On Hold' }],
      colors: { on_hold: 'yellow' },
    })

    expect(wrapper.find('.ui-badge').classes().join(' ')).toContain('warning')
  })

  /** No options, nothing to translate: the value stands, as it always did. */
  it('falls back to the value when no options are declared', () => {
    expect(render({ key: 'status', label: 'Status', type: 'badge' }).text()).toContain('on_hold')
  })
})
