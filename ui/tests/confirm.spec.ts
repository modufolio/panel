import { describe, expect, it, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ConfirmDialogHost from '../src/Components/Dialogs/ConfirmDialogHost.vue'
import { showConfirm, useConfirmDialog } from '../src/index'

function open() {
  const host = mount(ConfirmDialogHost, { attachTo: document.body })
  return host
}

describe('showConfirm', () => {
  beforeEach(() => {
    const { state } = useConfirmDialog()
    state.value = { open: false, title: '', message: '', confirmLabel: 'Confirm', tone: 'danger', resolve: null }
  })

  it('renders the question and resolves true when its button is clicked', async () => {
    const host = open()
    const answer = showConfirm({ title: 'Delete this issue?', message: 'It will go.', confirmLabel: 'Delete' })
    await host.vm.$nextTick()

    const dialog = document.body.textContent ?? ''
    expect(dialog).toContain('Delete this issue?')
    expect(dialog).toContain('It will go.')

    const confirmButton = [...document.body.querySelectorAll('button')]
      .find((button) => button.textContent?.trim() === 'Delete')
    expect(confirmButton).toBeDefined()
    confirmButton!.click()

    await expect(answer).resolves.toBe(true)
    host.unmount()
  })

  it('resolves false when Cancel is clicked', async () => {
    const host = open()
    const answer = showConfirm({ title: 'Delete?', message: '…' })
    await host.vm.$nextTick()

    const cancel = [...document.body.querySelectorAll('button')]
      .find((button) => button.textContent?.trim() === 'Cancel')
    expect(cancel).toBeDefined()
    cancel!.click()

    await expect(answer).resolves.toBe(false)
    host.unmount()
  })

  it('shows what was asked, with the label and tone given', async () => {
    open()
    void showConfirm({ title: 'Restore this user?', message: 'They can sign in again.', confirmLabel: 'Restore', tone: 'primary' })

    const { state } = useConfirmDialog()
    expect(state.value.title).toBe('Restore this user?')
    expect(state.value.confirmLabel).toBe('Restore')
    expect(state.value.tone).toBe('primary')
  })

  it('defaults to a destructive tone and a Confirm label', () => {
    void showConfirm({ title: 'Delete?', message: '…' })

    const { state } = useConfirmDialog()
    expect(state.value.tone).toBe('danger')
    expect(state.value.confirmLabel).toBe('Confirm')
  })

  it('never strands a question when a second one opens', async () => {
    const first = showConfirm({ title: 'First?', message: '…' })
    const second = showConfirm({ title: 'Second?', message: '…' })

    await expect(first).resolves.toBe(false)

    useConfirmDialog().confirm(true)
    await expect(second).resolves.toBe(true)
  })
})
