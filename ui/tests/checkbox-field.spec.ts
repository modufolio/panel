import { describe, it, expect } from 'vitest'
import { h } from 'vue'
import { mount } from '@vue/test-utils'
import { CheckboxField } from '../src/index'

describe('CheckboxField Component', () => {
  describe('Rendering', () => {
    it('should render checkbox input', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Accept terms',
          modelValue: false
        }
      })
      
      expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true)
    })

    it('should render with label', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Subscribe to newsletter',
          modelValue: false
        }
      })
      
      expect(wrapper.find('label').text()).toBe('Subscribe to newsletter')
    })

    it('should render with description', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Accept',
          description: 'I agree to the terms and conditions',
          modelValue: false
        }
      })

      const description = wrapper.findAll('p').filter(p => p.text() === 'I agree to the terms and conditions')
      expect(description.length).toBeGreaterThan(0)
    })

    it('should render with help text', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Accept',
          help: 'You must accept to continue',
          modelValue: false
        }
      })
      
      expect(wrapper.text()).toContain('You must accept to continue')
    })

    it('should render with error message', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Accept',
          error: 'You must accept the terms',
          modelValue: false
        }
      })
      
      const errorP = wrapper.find('.ui-field-error')
      expect(errorP.exists()).toBe(true)
      expect(errorP.text()).toBe('You must accept the terms')
    })
  })

  describe('Props', () => {
    it('should be unchecked by default', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false
        }
      })
      
      const input = wrapper.find('input[type="checkbox"]')
      expect((input.element as HTMLInputElement).checked).toBe(false)
    })

    it('should be checked when modelValue is true', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: true
        }
      })
      
      const input = wrapper.find('input[type="checkbox"]')
      expect((input.element as HTMLInputElement).checked).toBe(true)
    })

    it('should generate unique id by default', () => {
      // Both in one app: Vue's useId() counts per application, so two separate
      // mounts would legitimately restart the sequence.
      const wrapper = mount({
        render: () => h('div', [
          h(CheckboxField, { label: 'Test 1', modelValue: false }),
          h(CheckboxField, { label: 'Test 2', modelValue: false }),
        ]),
      })

      const [id1, id2] = wrapper.findAll('input').map((element) => element.attributes('id'))

      expect(id1).toBeTruthy()
      expect(id2).toBeTruthy()
      expect(id1).not.toBe(id2)
    })

    it('should accept custom id', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          id: 'custom-checkbox',
          label: 'Test',
          modelValue: false
        }
      })
      
      expect(wrapper.find('input').attributes('id')).toBe('custom-checkbox')
    })

    it('should be disabled when prop is true', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false,
          disabled: true
        }
      })
      
      expect(wrapper.find('input').attributes('disabled')).toBeDefined()
    })

    it('should show required indicator', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Accept terms',
          modelValue: false,
          required: true
        }
      })
      
      const label = wrapper.find('label')
      expect(label.classes()).toContain('after:content-[\'*\']')
    })
  })

  describe('Events', () => {
    it('should emit update:modelValue on change', async () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false
        }
      })
      
      const input = wrapper.find('input[type="checkbox"]')
      await input.setValue(true)
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
    })

    it('should emit false when unchecked', async () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: true
        }
      })
      
      const input = wrapper.find('input[type="checkbox"]')
      await input.setValue(false)
      
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false])
    })

    it('should toggle value on multiple clicks', async () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false
        }
      })
      
      const input = wrapper.find('input[type="checkbox"]')
      await input.setValue(true)
      await input.setValue(false)
      await input.setValue(true)
      
      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted).toHaveLength(3)
      expect(emitted?.[0]).toEqual([true])
      expect(emitted?.[1]).toEqual([false])
      expect(emitted?.[2]).toEqual([true])
    })
  })

  describe('Accessibility', () => {
    it('should link label to checkbox with for attribute', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          id: 'test-checkbox',
          label: 'Accept',
          modelValue: false
        }
      })
      
      const label = wrapper.find('label')
      expect(label.attributes('for')).toBe('test-checkbox')
    })

    it('should have aria-invalid when error exists', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false,
          error: 'Error message'
        }
      })
      
      const input = wrapper.find('input')
      expect(input.attributes('aria-invalid')).toBe('true')
    })

    it('should not have aria-invalid when no error', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false
        }
      })
      
      const input = wrapper.find('input')
      expect(input.attributes('aria-invalid')).toBe('false')
    })

    it('should have aria-required when required', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false,
          required: true
        }
      })
      
      const input = wrapper.find('input')
      expect(input.attributes('aria-required')).toBe('true')
    })

    it('should link to description with aria-describedby', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          id: 'test-checkbox',
          label: 'Accept',
          description: 'Read terms carefully',
          modelValue: false
        }
      })
      
      const input = wrapper.find('input')
      const describedby = input.attributes('aria-describedby')
      expect(describedby).toContain('test-checkbox-description')
    })

    it('should link to help text with aria-describedby', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          id: 'test-checkbox',
          label: 'Accept',
          help: 'Help text',
          modelValue: false
        }
      })
      
      const input = wrapper.find('input')
      const describedby = input.attributes('aria-describedby')
      expect(describedby).toContain('test-checkbox-help')
    })

    it('should link to error with aria-describedby', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          id: 'test-checkbox',
          label: 'Accept',
          error: 'Error message',
          modelValue: false
        }
      })
      
      const input = wrapper.find('input')
      const describedby = input.attributes('aria-describedby')
      expect(describedby).toContain('test-checkbox-error')
    })

    it('should have role="alert" on error message', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          error: 'Error message',
          modelValue: false
        }
      })
      
      const errorP = wrapper.find('.ui-field-error')
      expect(errorP.attributes('role')).toBe('alert')
    })
  })

  describe('CSS Classes', () => {
    it('should apply error border when error exists', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          error: 'Error',
          modelValue: false
        }
      })
      
      const input = wrapper.find('input')
      expect(input.classes()).toContain('border-danger-600')
    })

    it('should apply disabled styles when disabled', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          disabled: true,
          modelValue: false
        }
      })
      
      const input = wrapper.find('input')
      expect(input.classes()).toContain('disabled:bg-gray-50')
    })

    it('should make label cursor pointer', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false
        }
      })
      
      const label = wrapper.find('label')
      expect(label.classes()).toContain('cursor-pointer')
    })
  })

  describe('Layout', () => {
    it('should render checkbox before label', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false
        }
      })
      
      const container = wrapper.find('.ui-field-checkbox')
      const input = container.find('input')
      const label = container.find('label')
      
      expect(input.exists()).toBe(true)
      expect(label.exists()).toBe(true)
    })

    it('should render description below label', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Accept',
          description: 'Description text',
          modelValue: false
        }
      })
      
      const html = wrapper.html()
      const labelIndex = html.indexOf('Accept')
      const descIndex = html.indexOf('Description text')
      
      expect(descIndex).toBeGreaterThan(labelIndex)
    })
  })

  describe('Edge Cases', () => {
    it('should handle boolean modelValue correctly', async () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false
        }
      })

      expect(wrapper.find('input').element.checked).toBe(false)

      await wrapper.setProps({ modelValue: true })
      expect(wrapper.find('input').element.checked).toBe(true)
    })

    it('should render without optional props', () => {
      const wrapper = mount(CheckboxField, {
        props: {
          label: 'Test',
          modelValue: false
        }
      })
      
      expect(wrapper.exists()).toBe(true)
      expect(wrapper.find('input').exists()).toBe(true)
    })
  })
})
