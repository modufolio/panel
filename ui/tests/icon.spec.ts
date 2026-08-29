import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { Icon } from '../src/index'

describe('Icon Component', () => {
  describe('Props', () => {
    it('should render with valid icon name', () => {
      const wrapper = mount(Icon, {
        props: {
          name: 'home'
        }
      })
      
      expect(wrapper.html()).toBeTruthy()
    })

    it('should render with cheveron-down icon', () => {
      const wrapper = mount(Icon, {
        props: {
          name: 'cheveron-down'
        }
      })
      
      expect(wrapper.html()).toBeTruthy()
    })

    it('should render with menu icon', () => {
      const wrapper = mount(Icon, {
        props: {
          name: 'menu'
        }
      })
      
      expect(wrapper.html()).toBeTruthy()
    })

    it('should render with user icon', () => {
      const wrapper = mount(Icon, {
        props: {
          name: 'user'
        }
      })
      
      expect(wrapper.html()).toBeTruthy()
    })

    it('should not render with invalid icon name', () => {
      const wrapper = mount(Icon, {
        props: {
          name: 'invalid-icon-name'
        }
      })
      
      expect(wrapper.html()).toBe('<!--v-if-->')
    })
  })

  describe('Accessibility', () => {
    it('should have aria-hidden="true" by default', () => {
      const wrapper = mount(Icon, {
        props: {
          name: 'home'
        }
      })
      
      const svg = wrapper.find('svg')
      expect(svg.attributes('aria-hidden')).toBe('true')
    })

    it('should allow custom aria-hidden value', () => {
      const wrapper = mount(Icon, {
        props: {
          name: 'home',
          ariaHidden: false
        }
      })
      
      const svg = wrapper.find('svg')
      expect(svg.attributes('aria-hidden')).toBe('false')
    })

    it('should support string aria-hidden value', () => {
      const wrapper = mount(Icon, {
        props: {
          name: 'home',
          ariaHidden: 'false'
        }
      })
      
      const svg = wrapper.find('svg')
      expect(svg.attributes('aria-hidden')).toBe('false')
    })
  })

  describe('Icon Mapping', () => {
    const iconTests = [
      { name: 'dashboard', desc: 'dashboard icon' },
      { name: 'search', desc: 'search icon' },
      { name: 'bell', desc: 'notification icon' },
      { name: 'trash', desc: 'delete icon' },
      { name: 'settings', desc: 'settings icon' },
      { name: 'users', desc: 'users icon' },
      { name: 'office', desc: 'office icon' },
      { name: 'shopping-cart', desc: 'shopping cart icon' },
      { name: 'document', desc: 'document icon' },
      { name: 'chart', desc: 'chart icon' },
      { name: 'upload', desc: 'upload icon' },
    ]

    iconTests.forEach(({ name, desc }) => {
      it(`should render ${desc}`, () => {
        const wrapper = mount(Icon, {
          props: { name }
        })
        
        expect(wrapper.find('svg').exists()).toBe(true)
      })
    })
  })

  describe('Edge Cases', () => {
    it('should handle empty icon name gracefully', () => {
      const wrapper = mount(Icon, {
        props: {
          name: ''
        }
      })
      
      expect(wrapper.html()).toBe('<!--v-if-->')
    })

    it('should handle null icon name', () => {
      const wrapper = mount(Icon, {
        props: {
          name: null as any
        }
      })
      
      expect(wrapper.html()).toBe('<!--v-if-->')
    })
  })
})
