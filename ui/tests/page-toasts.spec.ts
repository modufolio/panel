import { describe, it, expect, beforeEach } from 'vitest'
import { showToast, showToastsIn } from '../src/Components/Notifications/pageToasts'
import { useToastStore } from '../src/Components/Notifications/useToast'

describe('server toasts', () => {
  beforeEach(() => {
    const store = useToastStore()
    for (const toast of [...store.toasts.value]) store.remove(toast.id)
  })

  it('shows every toast a JSON reply carries', () => {
    showToastsIn({ data: {}, _toasts: [
      { type: 'success', message: 'Saved.' },
      { type: 'error', message: 'Not that one.' },
    ] })

    const shown = useToastStore().toasts.value
    expect(shown.map((t) => [t.type, t.message])).toEqual([
      ['success', 'Saved.'],
      ['error', 'Not that one.'],
    ])
  })

  it('reads a host\'s own flash key as info', () => {
    showToast({ type: 'notice', message: 'Rebuilt the index.' })

    expect(useToastStore().toasts.value[0]?.type).toBe('info')
  })

  it('ignores replies without toasts, and toasts without a message', () => {
    showToastsIn(null)
    showToastsIn({ data: [] })
    showToastsIn({ _toasts: 'nope' })
    showToastsIn({ _toasts: [{ type: 'success', message: '' }] })

    expect(useToastStore().toasts.value).toEqual([])
  })
})
