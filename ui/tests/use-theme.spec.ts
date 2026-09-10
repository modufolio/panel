import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'

/**
 * useTheme keeps module-level state — one class on <html>, one stored
 * preference — so each case re-imports it against a fresh matchMedia and a
 * fresh localStorage rather than sharing a singleton across tests.
 */

let systemPrefersDark = false
const listeners: Array<(event: { matches: boolean }) => void> = []

function stubMatchMedia() {
  listeners.length = 0
  vi.stubGlobal(
    'matchMedia',
    vi.fn(() => ({
      matches: systemPrefersDark,
      addEventListener: (_: string, fn: (event: { matches: boolean }) => void) => {
        listeners.push(fn)
      },
      removeEventListener: () => {},
    })),
  )
}

async function loadTheme() {
  vi.resetModules()
  stubMatchMedia()
  return import('@/Composables/useTheme')
}

beforeEach(() => {
  localStorage.clear()
  document.documentElement.className = ''
  systemPrefersDark = false
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('useTheme', () => {
  it('defaults to dark, so the panel opens the way an image tool should', async () => {
    const { useTheme } = await loadTheme()
    const { theme, preference } = useTheme()

    expect(preference.value).toBe('system')
    expect(theme.value).toBe('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('honours a stored preference over the default', async () => {
    localStorage.setItem('panel.theme', 'light')

    const { useTheme } = await loadTheme()

    expect(useTheme().theme.value).toBe('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('ignores a stored value that is not a preference', async () => {
    localStorage.setItem('panel.theme', 'sepia')

    const { useTheme } = await loadTheme()

    expect(useTheme().preference.value).toBe('system')
  })

  it('writes the class and the store when the user picks', async () => {
    const { useTheme } = await loadTheme()
    const { set, theme } = useTheme()

    set('light')

    expect(theme.value).toBe('light')
    expect(localStorage.getItem('panel.theme')).toBe('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('follows the OS while the preference is system', async () => {
    systemPrefersDark = false
    const { useTheme, setDefaultTheme } = await loadTheme()
    setDefaultTheme('system')

    expect(useTheme().theme.value).toBe('light')

    for (const notify of listeners) notify({ matches: true })
    await Promise.resolve()

    expect(useTheme().theme.value).toBe('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('follows the OS when the user picks system, whatever the panel default is', async () => {
    // The default is `dark`, which is not `system` — picking System has to
    // mean the OS rather than falling back to that default.
    const { useTheme } = await loadTheme()
    useTheme().set('system')

    expect(useTheme().theme.value).toBe('light')

    for (const notify of listeners) notify({ matches: true })
    await Promise.resolve()

    expect(useTheme().theme.value).toBe('dark')
  })

  it('reads a stored system the same way, since it was a deliberate pick', async () => {
    localStorage.setItem('panel.theme', 'system')
    systemPrefersDark = false

    const { useTheme } = await loadTheme()

    expect(useTheme().theme.value).toBe('light')
  })

  it('stops following the OS once the user has chosen', async () => {
    const { useTheme, setDefaultTheme } = await loadTheme()
    setDefaultTheme('system')
    useTheme().set('light')

    for (const notify of listeners) notify({ matches: true })
    await Promise.resolve()

    expect(useTheme().theme.value).toBe('light')
  })

  it('applies the consumer default when nothing is stored', async () => {
    const { useTheme, setDefaultTheme } = await loadTheme()

    setDefaultTheme('light')
    await Promise.resolve()

    expect(useTheme().theme.value).toBe('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('survives localStorage throwing, as it does in a private window', async () => {
    const getItem = vi
      .spyOn(Storage.prototype, 'getItem')
      .mockImplementation(() => {
        throw new Error('denied')
      })
    const setItem = vi
      .spyOn(Storage.prototype, 'setItem')
      .mockImplementation(() => {
        throw new Error('denied')
      })

    const { useTheme } = await loadTheme()
    const { preference, set, theme } = useTheme()

    expect(preference.value).toBe('system')

    expect(() => set('light')).not.toThrow()
    expect(theme.value).toBe('light')

    getItem.mockRestore()
    setItem.mockRestore()
  })
})
