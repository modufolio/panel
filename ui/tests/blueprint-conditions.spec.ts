import { describe, it, expect } from 'vitest'
import { ref } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { evaluateCondition, useBlueprint, BlueprintForm, defineBlueprint, resolveFieldComponent, type FieldDef } from '../src/index'

describe('conditions', () => {
  const form = { status: 'published', count: 5, tags: ['a', 'b'], cover: '', title: 'Hi' }

  it('treats a two-element tuple as equality', () => {
    expect(evaluateCondition(['status', 'published'], form)).toBe(true)
    expect(evaluateCondition(['status', 'draft'], form)).toBe(false)
  })

  it('supports comparison operators', () => {
    expect(evaluateCondition(['count', '>', 3], form)).toBe(true)
    expect(evaluateCondition(['count', '<=', 4], form)).toBe(false)
    expect(evaluateCondition(['status', '!=', 'draft'], form)).toBe(true)
  })

  it('distinguishes a value-less operator from an implicit equality', () => {
    expect(evaluateCondition(['cover', 'empty'], form)).toBe(true)
    expect(evaluateCondition(['cover', 'not_empty'], form)).toBe(false)
    expect(evaluateCondition(['title', 'not_empty'], form)).toBe(true)
    // 'empty' as a literal value to compare against, not as an operator
    expect(evaluateCondition(['status', '==', 'empty'], form)).toBe(false)
  })

  it('supports membership in both directions', () => {
    expect(evaluateCondition(['status', 'in', ['draft', 'published']], form)).toBe(true)
    expect(evaluateCondition(['status', 'not_in', ['draft']], form)).toBe(true)
    expect(evaluateCondition(['tags', 'contains', 'a'], form)).toBe(true)
    expect(evaluateCondition(['tags', 'contains', 'z'], form)).toBe(false)
  })

  it('composes with all / any, including nested groups', () => {
    expect(evaluateCondition({ all: [['status', 'published'], ['count', '>', 3]] }, form)).toBe(true)
    expect(evaluateCondition({ all: [['status', 'published'], ['count', '>', 9]] }, form)).toBe(false)
    expect(evaluateCondition({ any: [['status', 'draft'], ['count', '>', 3]] }, form)).toBe(true)
    expect(evaluateCondition({ any: [{ all: [['status', 'draft'], ['count', '>', 3]] }, ['title', 'Hi']] }, form)).toBe(true)
  })

  it('still accepts a predicate function', () => {
    expect(evaluateCondition((f) => f.status === 'published', form)).toBe(true)
  })

  it('fails open on an unknown operator rather than hiding the field', () => {
    // A typo should surface as a stray field, not a silently missing one.
    expect(evaluateCondition(['status', 'equalz' as never, 'published'], form)).toBe(true)
  })
})

describe('useBlueprint reactivity', () => {
  const fields = defineBlueprint([
    { type: 'text', key: 'title', label: 'Title' },
    { type: 'text', key: 'reason', label: 'Reason', when: ['status', 'rejected'] },
  ] satisfies FieldDef[])

  it('re-evaluates conditions when the form object is replaced', () => {
    // Consumers emit a new object per change; a captured snapshot would freeze
    // visibility at the initial values.
    const model = ref<Record<string, unknown>>({ status: 'pending', title: '', reason: '' })
    const { visibleFields } = useBlueprint(() => fields, () => model.value)

    expect(visibleFields.value.map((f) => f.key)).toEqual(['title'])

    model.value = { ...model.value, status: 'rejected' }

    expect(visibleFields.value.map((f) => f.key)).toEqual(['title', 'reason'])
  })

  it('omits hidden fields from visibleData while keeping them in the model', () => {
    const model = ref<Record<string, unknown>>({ status: 'pending', title: 'a', reason: 'kept' })
    const { visibleData } = useBlueprint(() => fields, () => model.value)

    expect(visibleData.value).toEqual({ title: 'a' })
    // The value survives in the model, so unhiding restores it.
    expect(model.value.reason).toBe('kept')

    model.value = { ...model.value, status: 'rejected' }
    expect(visibleData.value).toEqual({ title: 'a', reason: 'kept' })
  })
})

describe('BlueprintForm', () => {
  it('shows and hides fields as the model changes', async () => {
    const fields = defineBlueprint([
      { type: 'text', key: 'title', label: 'Title' },
      { type: 'text', key: 'reason', label: 'Reason', when: ['status', 'rejected'] },
    ] satisfies FieldDef[])

    const wrapper = mount(BlueprintForm, {
      props: { fields, modelValue: { status: 'pending', title: '', reason: '' } },
    })

    // Field components are resolved with defineAsyncComponent, so nothing is
    // in the DOM until those promises settle — without this the "hidden"
    // assertion below would pass against an empty render.
    await flushPromises()

    expect(wrapper.text()).toContain('Title')
    expect(wrapper.text()).not.toContain('Reason')

    await wrapper.setProps({ modelValue: { status: 'rejected', title: '', reason: '' } })
    await flushPromises()

    expect(wrapper.text()).toContain('Reason')
  })
})

describe('field registry coverage', () => {
  // Every type a PHP FieldType can emit must resolve, or the form throws
  // "Unknown field type" at render. `tags` shipped as a component and as
  // App\Panel\Field\TagsType without ever being registered.
  it.each(['text', 'textarea', 'select', 'multiselect', 'toggle', 'checkbox', 'range',
    'date', 'datetime', 'time', 'date-range', 'file', 'color', 'belongs-to', 'repeater',
    'toggle-buttons', 'tags'])(
    'resolves the %s field type', async (type) => {
      await expect(resolveFieldComponent(type)).resolves.toBeTruthy()
    })
})
