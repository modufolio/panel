import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import BlueprintForm from '../src/Components/Fields/BlueprintForm.vue'

/**
 * Row-addressed server errors reach their row.
 *
 * The server answers a bad repeater row with a dotted key — `lines.1.name` —
 * and fieldProps fans those out to the container. This pins the seam that
 * silently dropped them: shownErrors used to filter to exact field keys, so a
 * dotted error survived the server round trip and then vanished client-side.
 */

const FIELDS = [
  {
    key: 'lines',
    type: 'repeater',
    label: 'Lines',
    fields: [
      { key: 'name', type: 'text', label: 'Name' },
    ],
  },
]

const MODEL = {
  lines: [
    { id: '1', name: 'Flour' },
    { id: '2', name: 'Flour' },
  ],
}

async function mountForm(errors: Record<string, string>) {
  const wrapper = mount(BlueprintForm, {
    props: {
      modelValue: { ...MODEL, lines: MODEL.lines.map((row) => ({ ...row })) },
      fields: FIELDS,
      errors,
    },
  })

  // The field components resolve through defineAsyncComponent, whose dynamic
  // import goes through the module runner's transform — real I/O, not a
  // microtask. Wait for the rows rather than guessing a delay.
  // Two async layers deep: the repeater itself, then the sub-field
  // components inside its rows — wait until the innermost inputs exist.
  await vi.waitFor(() => {
    expect(wrapper.findAll('.ui-repeater-item input').length).toBe(MODEL.lines.length)
  }, { timeout: 4000 })
  await flushPromises()

  return wrapper
}

describe('BlueprintForm row-addressed errors', () => {
  it('pins a dotted server error to its row', async () => {
    const wrapper = await mountForm({ 'lines.1.name': 'Another row already uses this.' })

    const rows = wrapper.findAll('.ui-repeater-item')
    expect(rows).toHaveLength(2)

    expect(rows[0].text()).not.toContain('Another row already uses this.')
    expect(rows[1].text()).toContain('Another row already uses this.')
  })

  it('clears row errors once the container is edited', async () => {
    const wrapper = await mountForm({ 'lines.1.name': 'Another row already uses this.' })

    const input = wrapper.findAll('.ui-repeater-item')[1].find('input')
    await input.setValue('Butter')
    await flushPromises()

    // Any edit may have been the fix, and a stale message pinned to a
    // reordered list would point at the wrong line.
    expect(wrapper.text()).not.toContain('Another row already uses this.')
  })

  it('shows errors that arrive after the field was edited — the submit round trip', async () => {
    const wrapper = await mountForm({})

    // The user edits a row, submits, and the server answers with a verdict
    // about that edit. "Edited before the error existed" must not hide it.
    const input = wrapper.findAll('.ui-repeater-item')[1].find('input')
    await input.setValue('Flour')
    await wrapper.setProps({ errors: { 'lines.1.name': 'Another row already uses this.' } })
    await flushPromises()

    expect(wrapper.findAll('.ui-repeater-item')[1].text()).toContain('Another row already uses this.')
  })

  it('leaves flat errors untouched by the fan-out', async () => {
    const wrapper = await mountForm({ 'lines.0.name': 'Bad row.', other: 'Unrelated.' })

    expect(wrapper.findAll('.ui-repeater-item')[0].text()).toContain('Bad row.')
    // A key for a field the form does not declare stays out of the repeater.
    expect(wrapper.text()).not.toContain('Unrelated.')
  })
})
