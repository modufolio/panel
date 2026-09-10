import { describe, it, expect, afterEach } from 'vitest'
import { mount, enableAutoUnmount } from '@vue/test-utils'
import { FacetedFilter } from '../src/index'

/**
 * The faceted filter is the trigger-plus-dropdown shape the toolbar uses for a
 * multi-value choice. It is fully controlled — every change leaves as an
 * emission and comes back as a prop — so the tests assert on what it emits,
 * not on state it keeps.
 */

// It registers a dismissable layer while open; one left mounted would take the
// next test's outside press.
enableAutoUnmount(afterEach)

const options = [
  { label: 'Draft', value: 'draft', count: 4 },
  { label: 'Published', value: 'published', count: 12 },
  { label: 'Archived', value: 'archived' },
]

function filter(modelValue: Array<string | number> = [], override: Record<string, unknown> = {}) {
  return mount(FacetedFilter, { props: { label: 'Status', options, modelValue, ...override } })
}

const trigger = (wrapper: ReturnType<typeof filter>) => wrapper.find('button')

/** v-show writes an inline display:none; isVisible() needs a document-attached mount to see it. */
function isOpen(wrapper: ReturnType<typeof filter>): boolean {
  return !(wrapper.find('.absolute').attributes('style') ?? '').includes('display: none')
}

function emitted(wrapper: ReturnType<typeof filter>): Array<string | number> {
  const events = wrapper.emitted('update:modelValue')
  expect(events, 'expected the filter to emit').toBeTruthy()
  return events!.at(-1)![0] as Array<string | number>
}

describe('FacetedFilter', () => {
  it('starts closed and opens on the trigger', async () => {
    const wrapper = filter()
    expect(isOpen(wrapper)).toBe(false)

    await trigger(wrapper).trigger('click')

    expect(isOpen(wrapper)).toBe(true)
  })

  it('closes again on a second press', async () => {
    const wrapper = filter()

    await trigger(wrapper).trigger('click')
    await trigger(wrapper).trigger('click')

    expect(isOpen(wrapper)).toBe(false)
  })

  describe('the trigger summarises the selection', () => {
    it('shows the label alone when nothing is chosen', () => {
      expect(trigger(filter()).text()).toBe('Status')
    })

    it('names up to two choices', () => {
      expect(trigger(filter(['draft', 'published'])).text()).toContain('Draft')
      expect(trigger(filter(['draft', 'published'])).text()).toContain('Published')
    })

    it('counts them instead once there are more than two', () => {
      const text = trigger(filter(['draft', 'published', 'archived'])).text()

      expect(text).toContain('3 selected')
      expect(text).not.toContain('Draft')
    })

    it('ignores a value the option list no longer offers', () => {
      expect(trigger(filter(['gone'])).text()).toBe('Status')
    })
  })

  it('marks the trigger as carrying a value', () => {
    expect(trigger(filter()).classes()).toContain('bg-surface')
    expect(trigger(filter(['draft'])).classes()).toContain('bg-primary-surface')
  })

  it('adds a value that was not selected', async () => {
    const wrapper = filter(['draft'])

    await trigger(wrapper).trigger('click')
    await wrapper.findAll('li button')[1]!.trigger('click')

    expect(emitted(wrapper)).toEqual(['draft', 'published'])
  })

  it('removes one that was', async () => {
    const wrapper = filter(['draft', 'published'])

    await trigger(wrapper).trigger('click')
    await wrapper.findAll('li button')[0]!.trigger('click')

    expect(emitted(wrapper)).toEqual(['published'])
  })

  it('never mutates the array it was handed', async () => {
    const modelValue = ['draft']
    const wrapper = filter(modelValue)

    await trigger(wrapper).trigger('click')
    await wrapper.findAll('li button')[1]!.trigger('click')

    expect(modelValue).toEqual(['draft'])
  })

  it('ticks exactly the options in force', async () => {
    const wrapper = filter(['published'])
    await trigger(wrapper).trigger('click')

    const ticked = wrapper.findAll('li button').map((button) => button.find('svg').exists())

    expect(ticked).toEqual([false, true, false])
  })

  it('shows a count only where the facet carried one', async () => {
    const wrapper = filter()
    await trigger(wrapper).trigger('click')

    const items = wrapper.findAll('li button')
    expect(items[0]!.text()).toContain('4')
    expect(items[2]!.text()).toBe('Archived')
  })

  describe('clearing', () => {
    it('is offered only while something is selected', async () => {
      const empty = filter()
      await trigger(empty).trigger('click')
      expect(empty.text()).not.toContain('Clear filter')

      const chosen = filter(['draft'])
      await trigger(chosen).trigger('click')
      expect(chosen.text()).toContain('Clear filter')
    })

    it('empties the selection and closes the dropdown', async () => {
      const wrapper = filter(['draft', 'published'])
      await trigger(wrapper).trigger('click')

      await wrapper.findAll('button').at(-1)!.trigger('click')

      expect(emitted(wrapper)).toEqual([])
      expect(isOpen(wrapper)).toBe(false)
    })
  })

  describe('searching', () => {
    const many = Array.from({ length: 7 }, (_, i) => ({ label: `Option ${i}`, value: i }))

    it('is offered only once the list is long enough to need it', async () => {
      const few = filter()
      await trigger(few).trigger('click')
      expect(few.find('input').exists()).toBe(false)

      const lots = filter([], { options: many })
      await trigger(lots).trigger('click')
      expect(lots.find('input').exists()).toBe(true)
    })

    it('narrows the list, case-insensitively', async () => {
      const wrapper = filter([], { options: many })
      await trigger(wrapper).trigger('click')

      await wrapper.find('input').setValue('OPTION 3')

      expect(wrapper.findAll('li button')).toHaveLength(1)
      expect(wrapper.find('li button').text()).toContain('Option 3')
    })

    it('says so when nothing matches, rather than showing an empty list', async () => {
      const wrapper = filter([], { options: many })
      await trigger(wrapper).trigger('click')

      await wrapper.find('input').setValue('nothing')

      expect(wrapper.findAll('li button')).toHaveLength(0)
      expect(wrapper.text()).toContain('No results')
    })

    it('leaves a selection made before the search in place', async () => {
      const wrapper = filter([0], { options: many })
      await trigger(wrapper).trigger('click')
      await wrapper.find('input').setValue('Option 3')

      await wrapper.find('li button').trigger('click')

      expect(emitted(wrapper)).toEqual([0, 3])
    })
  })

  it('says No results when the schema sent no options at all', async () => {
    const wrapper = filter([], { options: [] })

    await trigger(wrapper).trigger('click')

    expect(wrapper.text()).toContain('No results')
  })
})
