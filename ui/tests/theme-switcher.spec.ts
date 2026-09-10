import { describe, it, expect, afterEach, beforeEach } from 'vitest'
import { mount, enableAutoUnmount } from '@vue/test-utils'
import { ThemeSwitcher, useTheme } from '../src/index'

/**
 * The control in the top bar. useTheme itself is covered by use-theme.spec.ts;
 * what is left here is the picker's contract — three options, the live one
 * marked, and a click that reaches `set`.
 *
 * The composable is module state, so each case resets it through its own API
 * rather than re-importing: `set('system')` is what the user would do anyway.
 */

// It teleports its menu and registers a dismissable layer; one left mounted
// would keep both in the next test's document.
enableAutoUnmount(afterEach)

function switcher() {
  return mount(ThemeSwitcher, { attachTo: document.body })
}

/** The menu is teleported, so it is read off the document rather than the wrapper. */
const items = () =>
  Array.from(document.body.querySelectorAll<HTMLElement>('[data-theme-option]'))

const item = (option: string) =>
  document.body.querySelector<HTMLElement>(`[data-theme-option="${option}"]`)!

async function open() {
  const wrapper = switcher()
  await wrapper.find('button').trigger('click')
  return wrapper
}

beforeEach(() => {
  localStorage.clear()
  document.documentElement.className = ''
  useTheme().set('system')
})

describe('ThemeSwitcher', () => {
  it('offers the three states, in the order the composable orders them', async () => {
    await open()

    expect(items().map((el) => el.dataset.themeOption)).toEqual(['system', 'light', 'dark'])
    expect(
      items().map((el) => el.querySelector('span')?.textContent?.trim()),
    ).toEqual(['System', 'Light', 'Dark'])
  })

  it('marks the live preference, and only it', async () => {
    useTheme().set('light')
    await open()

    expect(items().map((el) => el.getAttribute('aria-checked'))).toEqual([
      'false',
      'true',
      'false',
    ])
  })

  it('says what System resolves to, so the row is not a blank promise', async () => {
    useTheme().set('system')
    await open()

    // happy-dom reports no `prefers-color-scheme: dark`, so System is light.
    expect(item('system').textContent).toContain('Light')
  })

  it('drops that hint once the preference is explicit — there is nothing to resolve', async () => {
    useTheme().set('dark')
    await open()

    expect(item('system').textContent?.trim()).toBe('System')
  })

  it('sets the preference the clicked row names', async () => {
    // The menu closes on a pick, so the second choice reopens it — which is
    // also the only way to reach it as a user.
    const wrapper = await open()

    item('light').click()
    await wrapper.vm.$nextTick()

    expect(useTheme().preference.value).toBe('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)

    await wrapper.find('button').trigger('click')
    item('dark').click()
    await wrapper.vm.$nextTick()

    expect(useTheme().preference.value).toBe('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('labels the trigger with the state it is showing', async () => {
    useTheme().set('system')
    const wrapper = switcher()

    expect(wrapper.find('button').attributes('aria-label')).toBe('Theme: System (light)')

    useTheme().set('light')
    await wrapper.vm.$nextTick()

    expect(wrapper.find('button').attributes('aria-label')).toBe('Theme: Light')
  })
})
