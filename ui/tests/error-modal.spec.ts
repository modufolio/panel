import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ErrorModal from '../src/Components/Dialogs/ErrorModal.vue'
import { showErrorModal, closeErrorModal, useErrorModal } from '../src/Components/Notifications/errorModal'

/**
 * The dialog is driven entirely by module state, because what writes to it —
 * the router's exception handlers — lives outside the component tree. So the
 * thing worth testing is that a `showErrorModal()` from anywhere reaches a
 * mounted dialog, and that dismissing it leaves nothing behind.
 */
describe('ErrorModal', () => {
  beforeEach(() => closeErrorModal())

  it('shows nothing until something fails', () => {
    const wrapper = mount(ErrorModal, { attachTo: document.body })

    expect(document.body.textContent).not.toContain('Error')
    wrapper.unmount()
  })

  it('renders what was reported, status included, and closes on dismiss', async () => {
    const wrapper = mount(ErrorModal, { attachTo: document.body })

    showErrorModal({ status: 503, title: 'Server busy', message: 'Try again in a moment.' })
    await wrapper.vm.$nextTick()

    expect(document.body.textContent).toContain('Server busy')
    expect(document.body.textContent).toContain('Try again in a moment.')
    // For the person reporting the bug, not the one reading the sentence.
    expect(document.body.textContent).toContain('Error 503')

    closeErrorModal()
    await wrapper.vm.$nextTick()

    expect(useErrorModal().state.value.open).toBe(false)
    wrapper.unmount()
  })

  it('falls back to its own words when the caller gave none', () => {
    showErrorModal()

    expect(useErrorModal().state.value).toMatchObject({
      open: true,
      status: 0,
      title: 'Something went wrong',
    })
  })
})
