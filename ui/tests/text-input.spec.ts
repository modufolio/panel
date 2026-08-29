import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { TextInput } from '../src/index'

describe('TextInput Component', () => {
  describe('Rendering', () => {
    it('should render text input', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.find('input').exists()).toBe(true)
    })

    it('should render with label', () => {
      const wrapper = mount(TextInput, {
        props: {
          label: 'Username',
          modelValue: ''
        }
      })
      
      expect(wrapper.find('label').text()).toBe('Username:')
    })

    it('should render without label when not provided', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.find('label').exists()).toBe(false)
    })

    it('should render with error message', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: '',
          error: 'This field is required'
        }
      })
      
      const errorDiv = wrapper.find('.form-error')
      expect(errorDiv.exists()).toBe(true)
      expect(errorDiv.text()).toBe('This field is required')
    })
  })

  describe('Props', () => {
    it('should accept and display modelValue', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: 'test value'
        }
      })
      
      const input = wrapper.find('input')
      expect(input.element.value).toBe('test value')
    })

    it('should set input type', () => {
      const wrapper = mount(TextInput, {
        props: {
          type: 'email',
          modelValue: ''
        }
      })
      
      expect(wrapper.find('input').attributes('type')).toBe('email')
    })

    it('should default to text type', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.find('input').attributes('type')).toBe('text')
    })

    it('should generate unique id by default', () => {
      const wrapper1 = mount(TextInput, {
        props: { modelValue: '' }
      })
      const wrapper2 = mount(TextInput, {
        props: { modelValue: '' }
      })
      
      const id1 = wrapper1.find('input').attributes('id')
      const id2 = wrapper2.find('input').attributes('id')
      
      expect(id1).toBeTruthy()
      expect(id2).toBeTruthy()
      expect(id1).not.toBe(id2)
    })

    it('should accept custom id', () => {
      const wrapper = mount(TextInput, {
        props: {
          id: 'custom-id',
          modelValue: ''
        }
      })
      
      expect(wrapper.find('input').attributes('id')).toBe('custom-id')
    })
  })

  describe('Events', () => {
    it('should emit update:modelValue on input', async () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      const input = wrapper.find('input')
      await input.setValue('new value')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['new value'])
    })

    it('should emit multiple update events', async () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      const input = wrapper.find('input')
      await input.setValue('first')
      await input.setValue('second')
      await input.setValue('third')
      
      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted).toHaveLength(3)
      expect(emitted?.[0]).toEqual(['first'])
      expect(emitted?.[1]).toEqual(['second'])
      expect(emitted?.[2]).toEqual(['third'])
    })
  })

  describe('Accessibility', () => {
    it('should have aria-invalid when error exists', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: '',
          error: 'Error message'
        }
      })
      
      const input = wrapper.find('input')
      expect(input.attributes('aria-invalid')).toBe('true')
    })

    it('should not have aria-invalid when no error', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      const input = wrapper.find('input')
      expect(input.attributes('aria-invalid')).toBe('false')
    })

    it('should link input to error message with aria-describedby', () => {
      const wrapper = mount(TextInput, {
        props: {
          id: 'test-input',
          modelValue: '',
          error: 'Error message'
        }
      })
      
      const input = wrapper.find('input')
      expect(input.attributes('aria-describedby')).toBe('test-input-error')
    })

    it('should have role="alert" on error message', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: '',
          error: 'Error message'
        }
      })
      
      const errorDiv = wrapper.find('.form-error')
      expect(errorDiv.attributes('role')).toBe('alert')
    })

    it('should link label to input with for attribute', () => {
      const wrapper = mount(TextInput, {
        props: {
          id: 'test-input',
          label: 'Username',
          modelValue: ''
        }
      })
      
      const label = wrapper.find('label')
      expect(label.attributes('for')).toBe('test-input')
    })
  })

  describe('Methods', () => {
    it('should have focus method', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.vm.focus).toBeDefined()
      expect(typeof wrapper.vm.focus).toBe('function')
    })

    it('should have select method', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.vm.select).toBeDefined()
      expect(typeof wrapper.vm.select).toBe('function')
    })

    it('should have setSelectionRange method', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.vm.setSelectionRange).toBeDefined()
      expect(typeof wrapper.vm.setSelectionRange).toBe('function')
    })
  })

  describe('CSS Classes', () => {
    it('should apply form-input class', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.find('input').classes()).toContain('form-input')
    })

    it('should apply error class when error exists', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: '',
          error: 'Error message'
        }
      })
      
      expect(wrapper.find('input').classes()).toContain('error')
    })

    it('should not apply error class when no error', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.find('input').classes()).not.toContain('error')
    })
  })

  describe('Attributes', () => {
    it('should pass through additional attributes', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        },
        attrs: {
          placeholder: 'Enter text',
          disabled: true
        }
      })
      
      const input = wrapper.find('input')
      expect(input.attributes('placeholder')).toBe('Enter text')
      expect(input.attributes('disabled')).toBeDefined()
    })

    it('should not pass class attribute to input directly', () => {
      const wrapper = mount(TextInput, {
        props: {
          modelValue: ''
        },
        attrs: {
          class: 'custom-wrapper-class'
        }
      })
      
      // Class should be on wrapper div, not input
      expect(wrapper.classes()).toContain('custom-wrapper-class')
      expect(wrapper.find('input').classes()).not.toContain('custom-wrapper-class')
    })
  })
})
