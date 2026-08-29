import { describe, it, expect } from 'vitest'
import { useNestedDrawerForm, type FieldConfig } from '../src/index'

const fields: FieldConfig[] = [
  { name: 'title', label: 'Title', type: 'text', required: true },
  { name: 'notes', label: 'Notes', type: 'textarea' },
]

const defaults = { title: '', notes: '' }

describe('useNestedDrawerForm dirty state', () => {
  it('is clean while closed, and while open but untouched', () => {
    const form = useNestedDrawerForm(fields, defaults)
    expect(form.isDirty.value).toBe(false)

    form.openForm('contact-1')
    expect(form.isDirty.value).toBe(false)
  })

  it('is dirty once a field is edited', () => {
    const form = useNestedDrawerForm(fields, defaults)
    form.openForm('contact-1')

    form.setFieldValue('title', 'Wedding')

    expect(form.isDirty.value).toBe(true)
  })

  /**
   * The save handler navigates — that visit is part of saving, not a
   * navigation away from unsaved work. Reporting dirty here made the
   * unsaved-changes guard prompt on every successful save.
   */
  it('is not dirty while the submit is in flight', async () => {
    const form = useNestedDrawerForm(fields, defaults)
    form.openForm('contact-1')
    form.setFieldValue('title', 'Wedding')

    let dirtyDuringSave: boolean | null = null

    await form.submit(async () => {
      // Where the handler would call router.visit().
      dirtyDuringSave = form.isDirty.value
    })

    expect(dirtyDuringSave).toBe(false)
  })

  it('closes the form once the save resolves', async () => {
    const form = useNestedDrawerForm(fields, defaults)
    form.openForm('contact-1')
    form.setFieldValue('title', 'Wedding')

    await form.submit(async () => {})

    expect(form.state.visible).toBe(false)
    expect(form.isDirty.value).toBe(false)
  })

  /** A failed save leaves the edits on screen, so the guard must come back. */
  it('is dirty again after a failed save', async () => {
    const form = useNestedDrawerForm(fields, defaults)
    form.openForm('contact-1')
    form.setFieldValue('title', 'Wedding')

    await form.submit(async () => { throw new Error('Server said no') })

    expect(form.state.visible).toBe(true)
    expect(form.state.saving).toBe(false)
    expect(form.serverError.value).toBe('Server said no')
    expect(form.isDirty.value).toBe(true)
  })

  it('does not submit when a required field is empty', async () => {
    const form = useNestedDrawerForm(fields, defaults)
    form.openForm('contact-1')

    let called = false
    await form.submit(async () => { called = true })

    expect(called).toBe(false)
    expect(form.state.visible).toBe(true)
  })
})
