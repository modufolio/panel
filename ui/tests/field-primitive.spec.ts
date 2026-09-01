import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import FieldPrimitive from '../src/Components/Fields/FieldPrimitive.vue'
import TextField from '../src/Components/Fields/TextField.vue'
import SelectField from '../src/Components/Fields/SelectField.vue'

/**
 * The frame every field renders inside.
 *
 * Worth its own coverage because the behaviour it centralises is exactly what
 * the fields used to get wrong one at a time: the description/error ids, the
 * `aria-describedby` that points at them, `role="alert"` on the message, and a
 * caption that has to be a `legend` when it captions more than one control.
 */
describe('FieldPrimitive', () => {
  const mountFrame = (props: Record<string, unknown> = {}) =>
    mount(FieldPrimitive, {
      props: { id: 'f1', ...props },
      slots: { default: '<input id="f1" />' },
    })

  it('renders nothing but the control when there is nothing to say', () => {
    const wrapper = mountFrame()

    expect(wrapper.find('label').exists()).toBe(false)
    expect(wrapper.find('.ui-field-help').exists()).toBe(false)
    expect(wrapper.find('.ui-field-error').exists()).toBe(false)
  })

  it('points the label at the control', () => {
    const wrapper = mountFrame({ label: 'Title' })

    expect(wrapper.find('label').attributes('for')).toBe('f1')
    expect(wrapper.find('label').text()).toBe('Title')
  })

  it('captions a fieldset with a legend, which takes no `for`', () => {
    const wrapper = mount(FieldPrimitive, {
      props: { id: 'f1', label: 'Published between', as: 'fieldset' },
      slots: { default: '<input /><input />' },
    })

    expect(wrapper.find('fieldset').exists()).toBe(true)
    expect(wrapper.find('legend').text()).toBe('Published between')
    // `<label for>` may only reference one control; a legend captions the group.
    expect(wrapper.find('legend').attributes('for')).toBeUndefined()
  })

  it('gives the help and error text ids derived from the control', () => {
    const wrapper = mountFrame({ help: 'Keep it short', error: 'Required' })

    expect(wrapper.find('.ui-field-help').attributes('id')).toBe('f1-help')
    expect(wrapper.find('.ui-field-error').attributes('id')).toBe('f1-error')
  })

  it('announces the error as an alert', () => {
    // It appears after a failed submit — a change the user did not initiate,
    // and silent without the role.
    expect(mountFrame({ error: 'Required' }).find('.ui-field-error').attributes('role')).toBe('alert')
  })

  describe('the slot props a control needs to be accessible', () => {
    const describedBy = (props: Record<string, unknown>) => {
      const wrapper = mount(FieldPrimitive, {
        props: { id: 'f1', ...props },
        slots: {
          default: `<template #default="s"><input id="f1" :aria-describedby="s.describedBy" :aria-invalid="s.invalid" /></template>`,
        },
      })

      return wrapper.find('input').attributes()
    }

    it('is undefined when there is no description — never an empty string', () => {
      // An empty aria-describedby describes the control as having a
      // description that is not there.
      expect(describedBy({})['aria-describedby']).toBeUndefined()
      expect(describedBy({})['aria-invalid']).toBeUndefined()
    })

    it('names the help text', () => {
      expect(describedBy({ help: 'Keep it short' })['aria-describedby']).toBe('f1-help')
    })

    it('names both, in reading order', () => {
      expect(describedBy({ help: 'Keep it short', error: 'Required' })['aria-describedby'])
        .toBe('f1-help f1-error')
    })

    it('marks the control invalid when there is an error', () => {
      expect(describedBy({ error: 'Required' })['aria-invalid']).toBe('true')
    })
  })
})

/**
 * The point of the refactor: the fields agree, because the markup is one
 * component rather than twenty-two copies of it.
 */
describe('fields share one frame', () => {
  const fields = [
    ['TextField', TextField],
    ['SelectField', SelectField],
  ] as const

  it.each(fields)('%s wires its own help and error the same way', (_name, component) => {
    const wrapper = mount(component, {
      props: {
        id: 'shared',
        label: 'Genre',
        help: 'Pick one',
        error: 'Required',
        required: true,
        // Ignored by the fields that take no options; SelectField needs them.
        options: ['Drama', 'Comedy'],
      },
    })

    const control = wrapper.find('input, select, textarea')

    expect(control.attributes('aria-describedby')).toBe('shared-help shared-error')
    expect(control.attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('.ui-field-error').attributes('role')).toBe('alert')
    expect(wrapper.find('label').attributes('for')).toBe('shared')
  })
})
