import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import RepeaterField from '../src/Components/Fields/RepeaterField.vue'

/**
 * The blueprint-mode contract StructureType (modufolio/panel) relies on.
 *
 * The server serializes a structure field as `type: 'repeater'` with `fields`
 * sub-declarations; this component is the other half of that contract. Pinned
 * here: rows render their declared sub-fields, edits/add/move/delete emit new
 * row arrays (never mutate), a fresh row is the declared keys with no id, and
 * row-scoped errors land on their sub-field.
 */

const FIELDS = [
  { key: 'name', type: 'text', label: 'Name' },
  { key: 'price', type: 'text', label: 'Price' },
]

const ROWS = [
  { name: 'LOVE SHOOT', price: '€285,-' },
  { name: 'BASIC', price: '€725,-' },
]

async function mountRepeater(extra: Record<string, unknown> = {}) {
  const wrapper = mount(RepeaterField, {
    props: {
      label: 'Cards',
      fields: FIELDS,
      modelValue: ROWS.map((row) => ({ ...row })),
      ...extra,
    },
  })
  // Sub-field components resolve through defineAsyncComponent, whose dynamic
  // import is real I/O — wait for the innermost inputs rather than guessing.
  await vi.waitFor(() => {
    expect(wrapper.findAll('.ui-repeater-item input').length).toBe(ROWS.length * FIELDS.length)
  }, { timeout: 4000 })
  await flushPromises()

  return wrapper
}

describe('RepeaterField blueprint mode', () => {
  it('renders one row per model entry, with the declared sub-fields filled', async () => {
    const wrapper = await mountRepeater()

    const rows = wrapper.findAll('.ui-repeater-item')
    expect(rows).toHaveLength(2)

    const inputs = rows[0]!.findAll('input')
    expect(inputs.map((i) => (i.element as HTMLInputElement).value)).toEqual(['LOVE SHOOT', '€285,-'])
  })

  it('editing a sub-field emits a new rows array with only that cell changed', async () => {
    const wrapper = await mountRepeater()

    await wrapper.findAll('.ui-repeater-item')[0]!.findAll('input')[1]!.setValue('€295,-')

    const emitted = wrapper.emitted('update:modelValue')!.at(-1)![0] as any[]
    expect(emitted[0]).toEqual({ name: 'LOVE SHOOT', price: '€295,-' })
    expect(emitted[1]).toEqual(ROWS[1])
  })

  it('adding appends the declared keys, empty and without an id', async () => {
    const wrapper = await mountRepeater()

    await wrapper.find('.ui-repeater-add button').trigger('click')

    const emitted = wrapper.emitted('update:modelValue')!.at(-1)![0] as any[]
    expect(emitted).toHaveLength(3)
    // No id: that absence is what tells the server "create, don't update".
    expect(emitted[2]).toEqual({ name: null, price: null })
  })

  it('delete removes exactly its row', async () => {
    const wrapper = await mountRepeater()

    await wrapper.findAll('.ui-repeater-item')[0]!.find('button[title="Delete"]').trigger('click')

    const emitted = wrapper.emitted('update:modelValue')!.at(-1)![0] as any[]
    expect(emitted).toEqual([ROWS[1]])
  })

  it('move swaps neighbours and clamps at the edges', async () => {
    const wrapper = await mountRepeater()
    const rows = wrapper.findAll('.ui-repeater-item')

    await rows[1]!.find('button[title="Move up"]').trigger('click')
    const swapped = wrapper.emitted('update:modelValue')!.at(-1)![0] as any[]
    expect(swapped.map((r) => r.name)).toEqual(['BASIC', 'LOVE SHOOT'])

    // First row's "up" is disabled — nothing to emit past the edge.
    expect(
      (rows[0]!.find('button[title="Move up"]').element as HTMLButtonElement).disabled
    ).toBe(true)
  })

  it('row-scoped errors land on their sub-field', async () => {
    const wrapper = await mountRepeater({
      nestedErrors: { '1.name': 'Name is required.' },
    })

    expect(wrapper.text()).toContain('Name is required.')
  })
})
