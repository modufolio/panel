import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import BelongsToSelect from '../src/Components/Fields/BelongsToSelect.vue'

/**
 * The input must name what is actually held.
 *
 * Typing filters the list, but typed-and-never-chosen text is not a
 * selection — dismissing the dropdown (Esc, clicking away) reverts the input
 * to the held value's label. Before this, the garbage text survived as the
 * display while the value underneath stayed put, and the submit proved the
 * input had been lying.
 */

const OPTIONS = [
  { id: 'u-butter', name: 'Butter' },
  { id: 'u-flour', name: 'Flour (T55)' },
]

function mountSelect(modelValue: string | null = 'u-butter') {
  return mount(BelongsToSelect, {
    props: {
      modelValue,
      label: 'Ingredient',
      options: OPTIONS,
    },
    attachTo: document.body,
  })
}

describe('BelongsToSelect revert-on-dismiss', () => {
  it('shows the held value\'s label on mount', async () => {
    const wrapper = mountSelect()
    await flushPromises()

    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Butter')
  })

  it('reverts typed-but-unchosen text on Escape', async () => {
    const wrapper = mountSelect()
    await flushPromises()

    const input = wrapper.find('input')
    await input.trigger('focus')
    await input.setValue('Flou')
    await input.trigger('keydown.esc')

    expect((input.element as HTMLInputElement).value).toBe('Butter')
    // And the value itself never moved.
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('reverts on a click outside the control', async () => {
    const wrapper = mountSelect()
    await flushPromises()

    const input = wrapper.find('input')
    await input.trigger('focus')
    await input.setValue('Flou')

    document.body.click()
    await flushPromises()

    expect((input.element as HTMLInputElement).value).toBe('Butter')

    wrapper.unmount()
  })

  it('reverts to empty when nothing is held', async () => {
    const wrapper = mountSelect(null)
    await flushPromises()

    const input = wrapper.find('input')
    await input.trigger('focus')
    await input.setValue('Flou')
    await input.trigger('keydown.esc')

    expect((input.element as HTMLInputElement).value).toBe('')
  })

  it('a real selection updates both the value and the display', async () => {
    const wrapper = mountSelect()
    await flushPromises()

    const input = wrapper.find('input')
    await input.trigger('focus')
    await input.setValue('Flour')

    await wrapper.findAll('.ui-belongs-to-option')[0].trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['u-flour'])
    expect((input.element as HTMLInputElement).value).toBe('Flour (T55)')
  })

  it('emptying the input still deselects — reverting must not undo it', async () => {
    const wrapper = mountSelect()
    await flushPromises()

    const input = wrapper.find('input')
    await input.trigger('focus')
    await input.setValue('')
    await input.trigger('keydown.esc')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
    expect((input.element as HTMLInputElement).value).toBe('')
  })
})
