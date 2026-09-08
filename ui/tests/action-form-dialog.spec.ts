import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const body = () => document.body
const form = () => {
  const element = document.body.querySelector('form')
  if (!element) throw new Error('no form in the document')
  return element
}
const submit = async () => {
  form().dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  await flushPromises()
}

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit, prefetch: vi.fn(), flushAll: vi.fn() } }))

const { default: ActionFormDialog } = await import('../src/Components/Dialogs/ActionFormDialog.vue')

/**
 * A `form` action's dialog renders the fields the server declared, posts
 * their values to the action's URL with its method — beside the extras a
 * bulk action carries — and a confirmation-only action shows its message.
 */
describe('ActionFormDialog', () => {
  beforeEach(() => {
    visit.mockClear()
    document.body.innerHTML = ''
  })

  const action = {
    label: 'Reject',
    submitLabel: 'Reject issue',
    method: 'patch',
    fields: [
      { key: 'reason', type: 'textarea', label: 'Reason' },
      { key: 'notify', type: 'toggle', label: 'Notify the reporter', default: true },
    ],
  }

  it('renders the declared fields and submits them with the method and extras', async () => {
    const wrapper = mount(ActionFormDialog, {
      attachTo: document.body,
      props: { action, url: '/panel/issues/7/reject', extra: { ids: [7] } },
    })
    await vi.waitFor(() => {
      expect(body().textContent).toContain('Notify the reporter')
    }, { timeout: 4000 })
    expect(body().textContent).toContain('Reason')

    await submit()

    expect(visit).toHaveBeenCalledTimes(1)
    const [url, options] = visit.mock.calls[0]
    expect(url).toBe('/panel/issues/7/reject')
    expect(options.method).toBe('patch')
    expect(options.data).toEqual({ ids: [7], reason: null, notify: true })
    expect(options.preserveScroll).toBe(true)

    wrapper.unmount()
  })

  it('shows the message with the count for a confirmation-only action, and posts', async () => {
    const wrapper = mount(ActionFormDialog, {
      attachTo: document.body,
      props: {
        action: { label: 'Archive', confirmMessage: 'Archive {count} selected issue(s)?' },
        url: '/panel/issues/archive',
        extra: { ids: [1, 2, 3] },
        count: 3,
      },
    })
    await flushPromises()

    expect(body().textContent).toContain('Archive 3 selected issue(s)?')

    await submit()
    const [, options] = visit.mock.calls[0]
    expect(options.method).toBe('post')
    expect(options.data).toEqual({ ids: [1, 2, 3] })

    wrapper.unmount()
  })

  it('puts validation errors on the fields and closes on success', async () => {
    const wrapper = mount(ActionFormDialog, {
      attachTo: document.body,
      props: { action, url: '/panel/issues/7/reject' },
    })
    await vi.waitFor(() => {
      expect(body().textContent).toContain('Reason')
    }, { timeout: 4000 })
    await submit()

    const [, options] = visit.mock.calls[0]
    options.onError({ reason: 'Say why.' })
    await flushPromises()
    expect(body().textContent).toContain('Say why.')

    options.onSuccess()
    expect(wrapper.emitted('close')).toHaveLength(1)

    wrapper.unmount()
  })
})
