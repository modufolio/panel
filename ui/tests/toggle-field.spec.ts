import { describe, it, expect } from 'vitest'
import { h } from 'vue'
import { mount } from '@vue/test-utils'
import { ToggleField } from '../src/index'

describe('ToggleField Component', () => {
  describe('Rendering', () => {
    it('should render toggle switch', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })
      
      const button = wrapper.find('button[role="switch"]')
      expect(button.exists()).toBe(true)
    })

    it('should render with label', () => {
      const wrapper = mount(ToggleField, {
        props: {
          label: 'Enable notifications',
          modelValue: false
        }
      })
      
      expect(wrapper.find('label').text()).toBe('Enable notifications')
    })

    it('should render with description', () => {
      const wrapper = mount(ToggleField, {
        props: {
          label: 'Enable',
          description: 'This will enable notifications',
          modelValue: false
        }
      })
      
      expect(wrapper.text()).toContain('This will enable notifications')
    })

    it('should render with help text', () => {
      const wrapper = mount(ToggleField, {
        props: {
          label: 'Enable',
          help: 'You can change this later',
          modelValue: false
        }
      })
      
      expect(wrapper.text()).toContain('You can change this later')
    })

    it('should render with error message', () => {
      const wrapper = mount(ToggleField, {
        props: {
          label: 'Enable',
          error: 'This field is required',
          modelValue: false
        }
      })
      
      const errorP = wrapper.find('.ui-field-error')
      expect(errorP.exists()).toBe(true)
      expect(errorP.text()).toBe('This field is required')
    })
  })

  describe('Props', () => {
    it('should be off (false) by default', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })
      
      const button = wrapper.find('button[role="switch"]')
      expect(button.attributes('aria-checked')).toBe('false')
    })

    it('should be on (true) when modelValue is true', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: true
        }
      })
      
      const button = wrapper.find('button[role="switch"]')
      expect(button.attributes('aria-checked')).toBe('true')
    })

    it('should generate unique id by default', () => {
      // Both in one app: Vue's useId() counts per application, so two separate
      // mounts would legitimately restart the sequence.
      const wrapper = mount({
        render: () => h('div', [
          h(ToggleField, { modelValue: false }),
          h(ToggleField, { modelValue: false }),
        ]),
      })

      const [id1, id2] = wrapper.findAll('button').map((element) => element.attributes('id'))

      expect(id1).toBeTruthy()
      expect(id2).toBeTruthy()
      expect(id1).not.toBe(id2)
    })

    it('should accept custom id', () => {
      const wrapper = mount(ToggleField, {
        props: {
          id: 'custom-toggle',
          modelValue: false
        }
      })
      
      expect(wrapper.find('button').attributes('id')).toBe('custom-toggle')
    })

    it('should be disabled when prop is true', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false,
          disabled: true
        }
      })
      
      expect(wrapper.find('button').attributes('disabled')).toBeDefined()
    })
  })

  describe('Events', () => {
    it('should emit update:modelValue on click', async () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })
      
      const button = wrapper.find('button')
      await button.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
    })

    it('should toggle from true to false', async () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: true
        }
      })
      
      const button = wrapper.find('button')
      await button.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false])
    })

    it('should toggle multiple times', async () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })

      const button = wrapper.find('button')
      await button.trigger('click') // emits true
      await wrapper.setProps({ modelValue: true })
      await button.trigger('click') // emits false
      await wrapper.setProps({ modelValue: false })
      await button.trigger('click') // emits true

      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted).toHaveLength(3)
      expect(emitted?.[0]).toEqual([true])
      expect(emitted?.[1]).toEqual([false])
      expect(emitted?.[2]).toEqual([true])
    })

    it('should not emit when disabled', async () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false,
          disabled: true
        }
      })
      
      const button = wrapper.find('button')
      await button.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    })
  })

  describe('Accessibility', () => {
    it('should have role="switch"', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('role')).toBe('switch')
    })

    it('should have aria-checked matching modelValue', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: true
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('aria-checked')).toBe('true')
    })

    it('should update aria-checked when toggled', async () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('aria-checked')).toBe('false')
      
      await wrapper.setProps({ modelValue: true })
      expect(button.attributes('aria-checked')).toBe('true')
    })

    it('should have aria-invalid when error exists', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false,
          error: 'Error message'
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('aria-invalid')).toBe('true')
    })

    it('should not have aria-invalid when no error', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('aria-invalid')).toBe('false')
    })

    it('should link to description with aria-describedby', () => {
      const wrapper = mount(ToggleField, {
        props: {
          id: 'test-toggle',
          description: 'Description text',
          modelValue: false
        }
      })
      
      const button = wrapper.find('button')
      const describedby = button.attributes('aria-describedby')
      expect(describedby).toContain('test-toggle-description')
    })

    it('should link to help text with aria-describedby', () => {
      const wrapper = mount(ToggleField, {
        props: {
          id: 'test-toggle',
          help: 'Help text',
          modelValue: false
        }
      })
      
      const button = wrapper.find('button')
      const describedby = button.attributes('aria-describedby')
      expect(describedby).toContain('test-toggle-help')
    })

    it('should link to error with aria-describedby', () => {
      const wrapper = mount(ToggleField, {
        props: {
          id: 'test-toggle',
          error: 'Error message',
          modelValue: false
        }
      })
      
      const button = wrapper.find('button')
      const describedby = button.attributes('aria-describedby')
      expect(describedby).toContain('test-toggle-error')
    })

    it('should have role="alert" on error message', () => {
      const wrapper = mount(ToggleField, {
        props: {
          error: 'Error message',
          modelValue: false
        }
      })
      
      const errorP = wrapper.find('.ui-field-error')
      expect(errorP.attributes('role')).toBe('alert')
    })

    it('should link label to toggle with for attribute', () => {
      const wrapper = mount(ToggleField, {
        props: {
          id: 'test-toggle',
          label: 'Enable feature',
          modelValue: false
        }
      })
      
      const label = wrapper.find('label')
      expect(label.attributes('for')).toBe('test-toggle')
    })
  })

  describe('Visual State', () => {
    it('should apply different background when on', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: true
        }
      })
      
      const button = wrapper.find('button')
      const html = button.html()
      // Should contain primary color classes when on
      expect(html).toContain('bg-primary')
    })

    it('should apply gray background when off', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })
      
      const button = wrapper.find('button')
      const html = button.html()
      // Should contain gray classes when off
      expect(html).toContain('bg-gray')
    })

    it('should translate switch handle when on', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: true
        }
      })
      
      const handle = wrapper.find('span')
      expect(handle.classes()).toContain('translate-x-5')
    })

    it('should not translate switch handle when off', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })
      
      const handle = wrapper.find('span')
      expect(handle.classes()).toContain('translate-x-0')
    })
  })

  describe('CSS Classes', () => {
    it('should apply disabled styles when disabled', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false,
          disabled: true
        }
      })
      
      const button = wrapper.find('button')
      expect(button.classes()).toContain('disabled:opacity-50')
    })

    it('should have cursor pointer on label', () => {
      const wrapper = mount(ToggleField, {
        props: {
          label: 'Test',
          modelValue: false
        }
      })
      
      const label = wrapper.find('label')
      expect(label.classes()).toContain('cursor-pointer')
    })
  })

  describe('Label Click', () => {
    it('should toggle when label is clicked', async () => {
      const wrapper = mount(ToggleField, {
        props: {
          label: 'Enable feature',
          modelValue: false
        }
      })
      
      const label = wrapper.find('label')
      await label.trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
    })
  })

  describe('Edge Cases', () => {
    it('should handle boolean modelValue correctly', async () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })

      expect(wrapper.find('button').attributes('aria-checked')).toBe('false')

      await wrapper.setProps({ modelValue: true })
      expect(wrapper.find('button').attributes('aria-checked')).toBe('true')
    })

    it('should render without optional props', () => {
      const wrapper = mount(ToggleField, {
        props: {
          modelValue: false
        }
      })
      
      expect(wrapper.exists()).toBe(true)
      expect(wrapper.find('button').exists()).toBe(true)
    })
  })
})
