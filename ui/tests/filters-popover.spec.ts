import { describe, it, expect, afterEach } from 'vitest'
import { mount, enableAutoUnmount } from '@vue/test-utils'
import { FilterPopover } from '../src/index'

/**
 * The shell the individual filters sit in: a trigger carrying the active
 * count, a teleported panel holding whatever the toolbar slotted in, and the
 * Reset/Apply pair beneath it. Apply only closes — the filters underneath are
 * live, so there is nothing to commit.
 */

// It registers a dismissable layer and teleports its panel; one left mounted
// would keep both in the next test's document.
enableAutoUnmount(afterEach)

function popover(props: Record<string, unknown> = {}, slot = '<div class="slotted">filters</div>') {
  return mount(FilterPopover, { props, slots: { default: slot }, attachTo: document.body })
}

const trigger = (wrapper: ReturnType<typeof popover>) => wrapper.find('button')

/** The teleported panel lives outside the wrapper, so it is read off the document. */
const panel = () => document.body.querySelector('.z-50') as HTMLElement | null
const isOpen = () => !!panel() && !(panel()!.style.display === 'none')

describe('FilterPopover', () => {
  it('starts closed', () => {
    popover()

    expect(isOpen()).toBe(false)
  })

  it('opens on the trigger and closes on a second press', async () => {
    const wrapper = popover()

    await trigger(wrapper).trigger('click')
    expect(isOpen()).toBe(true)

    await trigger(wrapper).trigger('click')
    expect(isOpen()).toBe(false)
  })

  it('renders whatever the toolbar slotted in', async () => {
    const wrapper = popover()

    await trigger(wrapper).trigger('click')

    expect(panel()!.querySelector('.slotted')).not.toBeNull()
  })

  describe('the active count', () => {
    it('is hidden at zero, so an unfiltered table carries no badge', () => {
      const wrapper = popover()

      expect(wrapper.find('.rounded-full').exists()).toBe(false)
    })

    it('shows once something is filtering', () => {
      const wrapper = popover({ activeFilterCount: 3 })

      expect(wrapper.find('.rounded-full').text()).toBe('3')
    })

    it('marks the trigger as carrying filters', () => {
      expect(trigger(popover()).classes()).toContain('bg-white')
      expect(trigger(popover({ activeFilterCount: 1 })).classes()).toContain('bg-primary-50')
    })
  })

  it('turns the chevron over while open', async () => {
    const wrapper = popover()
    const chevron = () => wrapper.findAll('svg').at(-1)!

    expect(chevron().classes()).not.toContain('rotate-180')

    await trigger(wrapper).trigger('click')

    expect(chevron().classes()).toContain('rotate-180')
  })

  it('takes its width from the prop, since a filter set can be wide', async () => {
    const wrapper = popover({ width: 480 })

    await trigger(wrapper).trigger('click')

    expect(panel()!.style.width).toBe('480px')
  })

  it('is 320 wide unless told otherwise', async () => {
    const wrapper = popover()

    await trigger(wrapper).trigger('click')

    expect(panel()!.style.width).toBe('320px')
  })

  describe('the footer', () => {
    it('asks the toolbar to reset, rather than reaching into the filters itself', async () => {
      const wrapper = popover()
      await trigger(wrapper).trigger('click')

      const reset = [...panel()!.querySelectorAll('button')].find((b) => b.textContent?.trim() === 'Reset')!
      reset.click()
      await wrapper.vm.$nextTick()

      expect(wrapper.emitted('reset')).toHaveLength(1)
      expect(isOpen()).toBe(true)
    })

    it('closes on Apply without emitting, the filters below being live already', async () => {
      const wrapper = popover()
      await trigger(wrapper).trigger('click')

      const apply = [...panel()!.querySelectorAll('button')].find((b) => b.textContent?.trim() === 'Apply')!
      apply.click()
      await wrapper.vm.$nextTick()

      expect(isOpen()).toBe(false)
      expect(wrapper.emitted('reset')).toBeUndefined()
    })
  })

  it('closes on Escape', async () => {
    const wrapper = popover()
    await trigger(wrapper).trigger('click')

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(isOpen()).toBe(false)
  })
})
