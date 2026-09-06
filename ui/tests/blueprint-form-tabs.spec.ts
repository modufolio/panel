import { describe, it, expect, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { BlueprintForm, type FieldDef, type FormLayout } from '../src/index'

/**
 * The server flattened its tabs and fieldsets into `group` and `fieldset` on
 * each field; the form rebuilds the structure from the visible fields.
 */
describe('BlueprintForm tabs and fieldsets', () => {
  const fields: FieldDef[] = [
    { key: 'title', type: 'text', label: 'Title' },
    { key: 'first_name', type: 'text', label: 'First name', group: 'general', fieldset: 'name' },
    { key: 'last_name', type: 'text', label: 'Last name', group: 'general', fieldset: 'name' },
    { key: 'email', type: 'text', label: 'Email', group: 'general', rules: [(value) => (value ? true : 'Required')] },
    { key: 'note', type: 'text', label: 'Note', group: 'notes' },
    { key: 'secret', type: 'text', label: 'Secret', group: 'hidden', when: ['title', '==', 'never'] },
  ]

  const layout: FormLayout = {
    tabs: [{ key: 'general', label: 'General' }, { key: 'notes', label: 'Notes' }, { key: 'hidden', label: 'Hidden' }],
    fieldsets: [{ key: 'name', label: 'Name', help: 'As on the passport' }],
  }

  /** v-show writes an inline display:none; isVisible() needs a document-attached mount to see it. */
  const shown = (wrapper: ReturnType<typeof mount>, key: string): boolean =>
    !(wrapper.find(`[data-tab-panel="${key}"]`).attributes('style') ?? '').includes('display: none')

  async function render(modelValue: Record<string, unknown> = {}) {
    const wrapper = mount(BlueprintForm, { props: { fields, layout, modelValue } })
    await vi.waitFor(() => {
      expect(wrapper.findAll('label').length).toBeGreaterThanOrEqual(4)
    }, { timeout: 4000 })
    await flushPromises()
    return wrapper
  }

  it('draws one tab per group that has a visible field, in the declared order', async () => {
    const wrapper = await render()
    const labels = wrapper.findAll('[role="tab"]').map((tab) => tab.text())

    expect(labels).toEqual(['General', 'Notes'])
  })

  it('renders fields without a group above the bar, and the first tab open', async () => {
    const wrapper = await render()

    expect(wrapper.find('[role="tab"][aria-selected="true"]').text()).toBe('General')
    expect(shown(wrapper, 'general')).toBe(true)
    expect(shown(wrapper, 'notes')).toBe(false)

    const labels = wrapper.findAll('label').map((l) => l.text())
    expect(labels[0]).toBe('Title')
  })

  it('switches tabs on click', async () => {
    const wrapper = await render()

    await wrapper.findAll('[role="tab"]')[1].trigger('click')

    expect(shown(wrapper, 'notes')).toBe(true)
    expect(shown(wrapper, 'general')).toBe(false)
  })

  it('boxes a fieldset with its legend and help', async () => {
    const wrapper = await render()
    const box = wrapper.find('.ui-fieldset')

    expect(box.find('legend').text()).toBe('Name')
    expect(box.text()).toContain('As on the passport')
    expect(box.findAll('label').map((l) => l.text())).toEqual(['First name', 'Last name'])
  })

  it('moves to the first tab with an error when the open one is clean', async () => {
    const wrapper = await render({ title: 'x', note: 'y' })

    await wrapper.findAll('[role="tab"]')[1].trigger('click')
    expect(shown(wrapper, 'notes')).toBe(true)

    ;(wrapper.vm as unknown as { validate: () => boolean }).validate()
    await flushPromises()

    expect(shown(wrapper, 'general')).toBe(true)
    expect(wrapper.find('.ui-form-tab-errors').text()).toBe('1')
  })
})
