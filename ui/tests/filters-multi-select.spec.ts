import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { MultiSelectFilter } from '../src/index'

/**
 * Unlike the faceted filter, this one keeps its own copy of the selection and
 * re-syncs from the prop, so the tests cover both directions: what a click
 * emits, and what a changed prop does to what is ticked.
 */

const options = [
  { label: 'Draft', value: 'draft', count: 4 },
  { label: 'Published', value: 'published', count: 12 },
  { label: 'Archived', value: 'archived' },
]

function filter(modelValue: unknown[] = [], override: Record<string, unknown> = {}) {
  return mount(MultiSelectFilter, { props: { options, modelValue, ...override } })
}

const boxes = (wrapper: ReturnType<typeof filter>) => wrapper.findAll('input[type="checkbox"]')
const checked = (wrapper: ReturnType<typeof filter>) =>
  boxes(wrapper).map((box) => (box.element as HTMLInputElement).checked)

function emitted(wrapper: ReturnType<typeof filter>): unknown[] {
  const events = wrapper.emitted('update:modelValue')
  expect(events, 'expected the filter to emit').toBeTruthy()
  return events!.at(-1)![0] as unknown[]
}

/** The action buttons under the divider: Select All and/or Clear. */
const actions = (wrapper: ReturnType<typeof filter>) => wrapper.findAll('.border-t button')

describe('MultiSelectFilter', () => {
  it('lists every option with its count, where one was given', () => {
    const wrapper = filter()

    expect(wrapper.findAll('label').at(-1)!.text()).toBe('Archived')
    expect(wrapper.text()).toContain('12')
  })

  it('accepts a bare string as an option, labelled by its own value', () => {
    const wrapper = filter([], { options: ['draft', 'published'] })

    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('draft')
  })

  it('ticks the values it was handed', () => {
    expect(checked(filter(['published']))).toEqual([false, true, false])
  })

  it('adds a value on tick', async () => {
    const wrapper = filter(['draft'])

    await boxes(wrapper)[1]!.setValue(true)

    expect(emitted(wrapper)).toEqual(['draft', 'published'])
  })

  it('removes one on untick', async () => {
    const wrapper = filter(['draft', 'published'])

    await boxes(wrapper)[0]!.setValue(false)

    expect(emitted(wrapper)).toEqual(['published'])
  })

  it('re-ticks from a changed prop, so a reset outside is reflected', async () => {
    const wrapper = filter(['draft'])

    await wrapper.setProps({ modelValue: ['archived'] })

    expect(checked(wrapper)).toEqual([false, false, true])
  })

  it('never mutates the array it was handed', async () => {
    const modelValue = ['draft']
    const wrapper = filter(modelValue)

    await boxes(wrapper)[1]!.setValue(true)

    expect(modelValue).toEqual(['draft'])
  })

  describe('select all and clear', () => {
    it('offers Select All only while something is unselected', async () => {
      expect(actions(filter()).map((b) => b.text())).toContain('Select All')

      const all = filter(['draft', 'published', 'archived'])
      expect(all.text()).not.toContain('Select All')
    })

    it('offers Clear only while something is selected, and counts it', () => {
      expect(filter().text()).not.toContain('Clear')
      expect(filter(['draft', 'published']).text()).toContain('Clear (2)')
    })

    it('selects every option, including ones a search is hiding', async () => {
      const wrapper = filter([])
      await wrapper.find('input[type="text"]').setValue('draft')

      await actions(wrapper)[0]!.trigger('click')

      expect(emitted(wrapper)).toEqual(['draft', 'published', 'archived'])
    })

    it('empties the selection', async () => {
      const wrapper = filter(['draft', 'published'])

      await actions(wrapper).at(-1)!.trigger('click')

      expect(emitted(wrapper)).toEqual([])
    })
  })

  describe('searching', () => {
    it('narrows the list, case-insensitively', async () => {
      const wrapper = filter()

      await wrapper.find('input[type="text"]').setValue('PUBLI')

      expect(boxes(wrapper)).toHaveLength(1)
      expect(wrapper.text()).toContain('Published')
    })

    it('distinguishes a search with no hits from an empty option list', async () => {
      const wrapper = filter()
      await wrapper.find('input[type="text"]').setValue('nothing')
      expect(wrapper.text()).toContain('No results found')

      expect(filter([], { options: [] }).text()).toContain('No options available')
    })

    it('can be turned off, which also removes the search box', () => {
      const wrapper = filter([], { searchable: false })

      expect(wrapper.find('input[type="text"]').exists()).toBe(false)
    })

    it('leaves a selection made before the search in place', async () => {
      const wrapper = filter(['archived'])
      await wrapper.find('input[type="text"]').setValue('draft')

      await boxes(wrapper)[0]!.setValue(true)

      expect(emitted(wrapper)).toEqual(['archived', 'draft'])
    })
  })
})
