import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { SelectFilter, TernaryFilter } from '../src/index'

/**
 * The two single-choice filters. Both are plain controlled selects — no
 * internal copy of the value — so what matters is the options they offer, the
 * empty choice that means "no filter", and the string they emit.
 */

const options = [
  { label: 'Admin', value: 'admin' },
  { label: 'Editor', value: 'editor' },
]

function selectFilter(props: Record<string, unknown> = {}) {
  return mount(SelectFilter, { props: { options, ...props } })
}

const values = (wrapper: { findAll: (s: string) => Array<{ attributes: (a: string) => string | undefined }> }) =>
  wrapper.findAll('option').map((option) => option.attributes('value'))

const labels = (wrapper: { findAll: (s: string) => Array<{ text: () => string }> }) =>
  wrapper.findAll('option').map((option) => option.text())

describe('SelectFilter', () => {
  it('leads with an empty choice, which is what "not filtering" is', () => {
    expect(values(selectFilter())[0]).toBe('')
    expect(labels(selectFilter())[0]).toBe('Select an option')
  })

  it('takes a placeholder for that choice', () => {
    expect(labels(selectFilter({ placeholder: 'Any role' }))[0]).toBe('Any role')
  })

  it('offers the options it was given, in order', () => {
    expect(values(selectFilter())).toEqual(['', 'admin', 'editor'])
    expect(labels(selectFilter())).toEqual(['Select an option', 'Admin', 'Editor'])
  })

  it('shows the value in force as the selection', () => {
    const wrapper = selectFilter({ modelValue: 'editor' })

    expect((wrapper.find('select').element as HTMLSelectElement).value).toBe('editor')
  })

  it('emits the chosen value', async () => {
    const wrapper = selectFilter({ modelValue: '' })

    await wrapper.find('select').setValue('admin')

    expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual(['admin'])
  })

  it('emits the empty string when the empty choice is picked back', async () => {
    const wrapper = selectFilter({ modelValue: 'admin' })

    await wrapper.find('select').setValue('')

    expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual([''])
  })

  it('renders a label only when it was given one', () => {
    expect(selectFilter().find('label').exists()).toBe(false)
    expect(selectFilter({ label: 'Role' }).find('label').text()).toBe('Role')
  })

  describe('a capped option list', () => {
    it('says so, rather than ending early and looking complete', () => {
      const wrapper = selectFilter({ optionsTruncated: true })

      expect(wrapper.text()).toContain('Showing the first 2')
    })

    it('stays quiet when the list is whole', () => {
      expect(selectFilter().text()).not.toContain('Showing the first')
    })
  })
})

function ternaryFilter(props: Record<string, unknown> = {}) {
  return mount(TernaryFilter, { props })
}

describe('TernaryFilter', () => {
  it('offers all, yes and no', () => {
    expect(labels(ternaryFilter())).toEqual(['All', 'Yes', 'No'])
  })

  it('sends true and false as the values, not the strings', () => {
    // `setValue` on a select matches by the option's DOM value, so the
    // stringified booleans are what the markup carries.
    expect(values(ternaryFilter())).toEqual(['', 'true', 'false'])
  })

  it('takes the wording and the values a schema declared', () => {
    const wrapper = ternaryFilter({
      placeholder: 'Any',
      trueLabel: 'With Deleted',
      trueValue: 'with',
      falseLabel: 'Only Deleted',
      falseValue: 'only',
    })

    expect(labels(wrapper)).toEqual(['Any', 'With Deleted', 'Only Deleted'])
    expect(values(wrapper)).toEqual(['', 'with', 'only'])
  })

  it('shows the value in force', () => {
    const wrapper = ternaryFilter({ trueValue: 'with', falseValue: 'only', modelValue: 'only' })

    expect((wrapper.find('select').element as HTMLSelectElement).value).toBe('only')
  })

  it('emits what the chosen option carries', async () => {
    const wrapper = ternaryFilter({ trueValue: 'with', falseValue: 'only' })

    await wrapper.find('select').setValue('with')

    expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual(['with'])
  })

  it('renders a label only when it was given one', () => {
    expect(ternaryFilter().find('label').exists()).toBe(false)
    expect(ternaryFilter({ label: 'Deleted' }).find('label').text()).toBe('Deleted')
  })
})
