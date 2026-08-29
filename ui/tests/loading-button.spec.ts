import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { LoadingButton } from '../src/index'

describe('LoadingButton Component', () => {
  describe('Rendering', () => {
    it('should render button element', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      expect(wrapper.find('button').exists()).toBe(true)
    })

    it('should render slot content', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        },
        slots: {
          default: 'Save Changes'
        }
      })
      
      expect(wrapper.text()).toBe('Save Changes')
    })

    it('should render loading spinner when loading', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        }
      })
      
      const spinner = wrapper.find('.btn-spinner')
      expect(spinner.exists()).toBe(true)
    })

    it('should not render spinner when not loading', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      const spinner = wrapper.find('.btn-spinner')
      expect(spinner.exists()).toBe(false)
    })
  })

  describe('Props', () => {
    it('should default to loading=false', () => {
      const wrapper = mount(LoadingButton)
      
      expect(wrapper.props('loading')).toBe(false)
    })

    it('should accept loading=true', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        }
      })
      
      expect(wrapper.props('loading')).toBe(true)
    })

    it('should accept boolean loading prop', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      expect(typeof wrapper.props('loading')).toBe('boolean')
    })
  })

  describe('Disabled State', () => {
    it('should be disabled when loading', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('disabled')).toBeDefined()
    })

    it('should not be disabled when not loading', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('disabled')).toBeUndefined()
    })

    it('should prevent clicks when disabled', async () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        }
      })
      
      const button = wrapper.find('button')
      await button.trigger('click')
      
      // Disabled buttons don't emit click events
      expect(wrapper.emitted('click')).toBeFalsy()
    })
  })

  describe('Loading State Transitions', () => {
    it('should show spinner when loading changes to true', async () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      expect(wrapper.find('.btn-spinner').exists()).toBe(false)
      
      await wrapper.setProps({ loading: true })
      
      expect(wrapper.find('.btn-spinner').exists()).toBe(true)
    })

    it('should hide spinner when loading changes to false', async () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        }
      })
      
      expect(wrapper.find('.btn-spinner').exists()).toBe(true)
      
      await wrapper.setProps({ loading: false })
      
      expect(wrapper.find('.btn-spinner').exists()).toBe(false)
    })

    it('should enable button when loading changes to false', async () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('disabled')).toBeDefined()
      
      await wrapper.setProps({ loading: false })
      
      expect(button.attributes('disabled')).toBeUndefined()
    })
  })

  describe('Layout', () => {
    it('should use flex layout', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      const button = wrapper.find('button')
      expect(button.classes()).toContain('flex')
    })

    it('should align items center', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      const button = wrapper.find('button')
      expect(button.classes()).toContain('items-center')
    })

    it('should have margin on spinner', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        }
      })
      
      const spinner = wrapper.find('.btn-spinner')
      expect(spinner.classes()).toContain('mr-2')
    })
  })

  describe('Content with Loading', () => {
    it('should show both spinner and text when loading', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        },
        slots: {
          default: 'Saving...'
        }
      })
      
      expect(wrapper.find('.btn-spinner').exists()).toBe(true)
      expect(wrapper.text()).toBe('Saving...')
    })

    it('should show only text when not loading', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        },
        slots: {
          default: 'Save'
        }
      })
      
      expect(wrapper.find('.btn-spinner').exists()).toBe(false)
      expect(wrapper.text()).toBe('Save')
    })
  })

  describe('Slots', () => {
    it('should render default slot', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        },
        slots: {
          default: 'Button Text'
        }
      })
      
      expect(wrapper.text()).toContain('Button Text')
    })

    it('should support HTML in slot', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        },
        slots: {
          default: '<span class="custom-text">Custom</span>'
        }
      })
      
      expect(wrapper.find('.custom-text').exists()).toBe(true)
    })

    it('should render empty slot', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      expect(wrapper.text()).toBe('')
    })
  })

  describe('Use Cases', () => {
    it('should work as submit button', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        },
        attrs: {
          type: 'submit'
        }
      })
      
      expect(wrapper.find('button').attributes('type')).toBe('submit')
    })

    it('should work with form submission', async () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        },
        slots: {
          default: 'Submit Form'
        }
      })
      
      const button = wrapper.find('button')
      expect(button.exists()).toBe(true)
      expect(button.text()).toBe('Submit Form')
    })

    it('should prevent double submission when loading', async () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        }
      })
      
      const button = wrapper.find('button')
      await button.trigger('click')
      await button.trigger('click')
      
      // No click events should be emitted when disabled
      expect(wrapper.emitted('click')).toBeFalsy()
    })
  })

  describe('Edge Cases', () => {
    it('should handle rapid loading toggles', async () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      await wrapper.setProps({ loading: true })
      await wrapper.setProps({ loading: false })
      await wrapper.setProps({ loading: true })
      await wrapper.setProps({ loading: false })
      
      expect(wrapper.find('button').exists()).toBe(true)
      expect(wrapper.find('.btn-spinner').exists()).toBe(false)
    })

    it('should handle undefined loading prop', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: undefined as any
        }
      })
      
      const button = wrapper.find('button')
      expect(button.exists()).toBe(true)
    })

    it('should render without any props', () => {
      const wrapper = mount(LoadingButton)
      
      expect(wrapper.find('button').exists()).toBe(true)
    })
  })

  describe('Accessibility', () => {
    it('should be a button element (implicit role)', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        }
      })
      
      expect(wrapper.find('button').element.tagName).toBe('BUTTON')
    })

    it('should indicate disabled state when loading', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: true
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('disabled')).toBeDefined()
    })

    it('should allow custom aria attributes', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        },
        attrs: {
          'aria-label': 'Save document'
        }
      })
      
      const button = wrapper.find('button')
      expect(button.attributes('aria-label')).toBe('Save document')
    })
  })

  describe('Integration', () => {
    it('should work with click handlers', async () => {
      const onClick = () => {}
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        },
        attrs: {
          onClick
        }
      })
      
      const button = wrapper.find('button')
      await button.trigger('click')
      
      // Button is clickable when not loading
      expect(button.attributes('disabled')).toBeUndefined()
    })

    it('should work with CSS classes', () => {
      const wrapper = mount(LoadingButton, {
        props: {
          loading: false
        },
        attrs: {
          class: 'btn btn-primary'
        }
      })
      
      const button = wrapper.find('button')
      expect(button.classes()).toContain('btn')
      expect(button.classes()).toContain('btn-primary')
    })
  })
})
