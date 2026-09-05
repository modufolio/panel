import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { BlueprintForm, type FieldDef } from '../src/index'

/** A separator holds its place in the grid, spans the row, and draws a rule only when asked to. */
describe('BlueprintForm separators', () => {
  const fields: FieldDef[] = [
    { key: 'first_name', type: 'text', label: 'First name' },
    { key: 'separator_1', type: 'separator', label: '', width: 'full', props: { separator: 'line' } },
    { key: 'email', type: 'text', label: 'Email' },
    { key: 'separator_2', type: 'separator', label: '', width: 'full', props: { separator: 'space' } },
    { key: 'note', type: 'text', label: 'Note' },
  ]

  async function render() {
    const wrapper = mount(BlueprintForm, { props: { fields, modelValue: {} } })
    // Field components load through a dynamic import. A fixed number of
    // flushes is not enough on a cold module cache (the first spec in a CI
    // run), so wait until every field, separators included, has mounted.
    await vi.waitFor(() => {
      expect(wrapper.findAll('label')).toHaveLength(3)
      expect(wrapper.findAll('.ui-field-separator')).toHaveLength(2)
    }, { timeout: 4000 })
    await flushPromises()
    return wrapper
  }

  it('renders one element per separator, spanning the row', async () => {
    const wrapper = await render()
    const separators = wrapper.findAll('.ui-field-separator')

    expect(separators).toHaveLength(2)
    separators.forEach((s) => expect(s.classes()).toContain('col-span-12'))
  })

  it('draws a rule for line and nothing for space', async () => {
    const wrapper = await render()
    const [line, space] = wrapper.findAll('.ui-field-separator')

    expect(line.classes()).toContain('border-t')
    expect(space.classes()).not.toContain('border-t')
  })

  it('renders no label or control for a separator', async () => {
    const wrapper = await render()

    expect(wrapper.findAll('label')).toHaveLength(3)
  })
})
