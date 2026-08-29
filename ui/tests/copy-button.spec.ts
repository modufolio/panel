import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { CopyButton } from '../src/index'

describe('CopyButton', () => {
  beforeEach(() => {
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: vi.fn().mockResolvedValue(undefined) },
    })
  })

  it('renders nothing for an empty value', () => {
    expect(mount(CopyButton, { props: { value: '' } }).find('button').exists()).toBe(false)
  })

  it('writes the value to the clipboard on click', async () => {
    const wrapper = mount(CopyButton, { props: { value: '555-0100' } })

    await wrapper.find('button').trigger('click')

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('555-0100')
  })

  it('does not let the click bubble to a surrounding link', async () => {
    const onParentClick = vi.fn()
    const wrapper = mount(
      { components: { CopyButton }, template: '<a @click="onParentClick"><CopyButton value="x" /></a>', setup: () => ({ onParentClick }) },
    )

    await wrapper.find('button').trigger('click')

    expect(onParentClick).not.toHaveBeenCalled()
  })
})
