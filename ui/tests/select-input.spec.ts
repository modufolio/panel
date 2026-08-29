import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { SelectInput } from '../src/index'

describe('SelectInput Component', () => {
  describe('Rendering', () => {
    it('should render select element', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.find('select').exists()).toBe(true)
    })

    it('should render with label', () => {
      const wrapper = mount(SelectInput, {
        props: {
          label: 'Country',
          modelValue: ''
        }
      })
      
      expect(wrapper.find('label').text()).toBe('Country:')
    })

    it('should render without label when not provided', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.find('label').exists()).toBe(false)
    })

    it('should render with error message', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: '',
          error: 'Please select an option'
        }
      })
      
      const errorDiv = wrapper.find('.form-error')
      expect(errorDiv.exists()).toBe(true)
      expect(errorDiv.text()).toBe('Please select an option')
    })
  })

  describe('Props', () => {
    it('should accept and display modelValue', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: 'option1'
        },
        slots: {
          default: '<option value="option1">Option 1</option>'
        }
      })
      
      const select = wrapper.find('select')
      expect(select.element.value).toBe('option1')
    })

    it('should generate unique id by default', () => {
      const wrapper1 = mount(SelectInput, {
        props: { modelValue: '' }
      })
      const wrapper2 = mount(SelectInput, {
        props: { modelValue: '' }
      })
      
      const id1 = wrapper1.find('select').attributes('id')
      const id2 = wrapper2.find('select').attributes('id')
      
      expect(id1).toBeTruthy()
      expect(id2).toBeTruthy()
      expect(id1).not.toBe(id2)
    })

    it('should accept custom id', () => {
      const wrapper = mount(SelectInput, {
        props: {
          id: 'custom-select',
          modelValue: ''
        }
      })
      
      expect(wrapper.find('select').attributes('id')).toBe('custom-select')
    })
  })

  describe('Events', () => {
    it('should emit update:modelValue on change', async () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        },
        slots: {
          default: `
            <option value="">Select...</option>
            <option value="opt1">Option 1</option>
            <option value="opt2">Option 2</option>
          `
        }
      })
      
      const select = wrapper.find('select')
      await select.setValue('opt1')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['opt1'])
    })

    it('should emit multiple change events', async () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        },
        slots: {
          default: `
            <option value="">Select...</option>
            <option value="opt1">Option 1</option>
            <option value="opt2">Option 2</option>
            <option value="opt3">Option 3</option>
          `
        }
      })
      
      const select = wrapper.find('select')
      await select.setValue('opt1')
      await select.setValue('opt2')
      await select.setValue('opt3')
      
      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted).toHaveLength(3)
      expect(emitted?.[0]).toEqual(['opt1'])
      expect(emitted?.[1]).toEqual(['opt2'])
      expect(emitted?.[2]).toEqual(['opt3'])
    })
  })

  describe('Accessibility', () => {
    it('should have aria-invalid when error exists', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: '',
          error: 'Error message'
        }
      })
      
      const select = wrapper.find('select')
      expect(select.attributes('aria-invalid')).toBe('true')
    })

    it('should not have aria-invalid when no error', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        }
      })
      
      const select = wrapper.find('select')
      expect(select.attributes('aria-invalid')).toBe('false')
    })

    it('should link select to error message with aria-describedby', () => {
      const wrapper = mount(SelectInput, {
        props: {
          id: 'test-select',
          modelValue: '',
          error: 'Error message'
        }
      })
      
      const select = wrapper.find('select')
      expect(select.attributes('aria-describedby')).toBe('test-select-error')
    })

    it('should have role="alert" on error message', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: '',
          error: 'Error message'
        }
      })
      
      const errorDiv = wrapper.find('.form-error')
      expect(errorDiv.attributes('role')).toBe('alert')
    })

    it('should link label to select with for attribute', () => {
      const wrapper = mount(SelectInput, {
        props: {
          id: 'test-select',
          label: 'Country',
          modelValue: ''
        }
      })
      
      const label = wrapper.find('label')
      expect(label.attributes('for')).toBe('test-select')
    })

    it('should use native select element (no custom chevron SVG)', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        }
      })

      // SelectInput uses a native <select> element with browser-native dropdown styling
      expect(wrapper.find('select').exists()).toBe(true)
      expect(wrapper.find('svg').exists()).toBe(false)
    })
  })

  describe('Slots', () => {
    it('should render slot content as options', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        },
        slots: {
          default: `
            <option value="1">Option 1</option>
            <option value="2">Option 2</option>
            <option value="3">Option 3</option>
          `
        }
      })
      
      const options = wrapper.findAll('option')
      expect(options).toHaveLength(3)
    })
  })

  describe('CSS Classes', () => {
    it('should apply form-select class', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        }
      })

      expect(wrapper.find('select').classes()).toContain('form-select')
    })

    it('should apply error class when error exists', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: '',
          error: 'Error message'
        }
      })
      
      expect(wrapper.find('select').classes()).toContain('error')
    })

    it('should not apply error class when no error', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        }
      })
      
      expect(wrapper.find('select').classes()).not.toContain('error')
    })
  })

  describe('Attributes', () => {
    it('should pass through additional attributes', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        },
        attrs: {
          disabled: true,
          required: true
        }
      })
      
      const select = wrapper.find('select')
      expect(select.attributes('disabled')).toBeDefined()
      expect(select.attributes('required')).toBeDefined()
    })

    it('should not pass class attribute to select directly', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        },
        attrs: {
          class: 'custom-wrapper-class'
        }
      })
      
      // Class should be on wrapper div, not select
      expect(wrapper.classes()).toContain('custom-wrapper-class')
      expect(wrapper.find('select').classes()).not.toContain('custom-wrapper-class')
    })
  })

  describe('Edge Cases', () => {
    it('should handle empty modelValue', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: ''
        }
      })
      
      const select = wrapper.find('select')
      expect(select.element.value).toBe('')
    })

    it('should handle null modelValue', () => {
      const wrapper = mount(SelectInput, {
        props: {
          modelValue: null as any
        }
      })
      
      expect(wrapper.find('select').exists()).toBe(true)
    })
  })
})
