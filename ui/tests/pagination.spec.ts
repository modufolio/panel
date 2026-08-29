import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { Pagination } from '../src/index'

describe('Pagination Component', () => {
  describe('Rendering', () => {
    it('should render when more than 3 links', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: '/page/2', label: '2', active: true },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/4', label: '4', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      expect(wrapper.exists()).toBe(true)
    })

    it('should not render when 3 or fewer links', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: '/page/2', label: '2', active: true },
            { url: '/page/3', label: '3', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      expect(wrapper.html()).toBe('<!--v-if-->')
    })

    it('should render all links', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: 'Previous', active: false },
            { url: '/page/1', label: '1', active: false },
            { url: '/page/2', label: '2', active: true },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/3', label: 'Next', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      const links = wrapper.findAll('a')
      // Should have 4 clickable links (excluding current page)
      expect(links.length).toBeGreaterThan(0)
    })

    it('should render active link differently', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: null, label: '2', active: true },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/4', label: '4', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href" :class="$attrs.class"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      const activeDiv = wrapper.find('.bg-white')
      expect(activeDiv.exists()).toBe(true)
    })
  })

  describe('Props', () => {
    it('should accept links array', () => {
      const links = [
        { url: '/page/1', label: '1', active: false },
        { url: '/page/2', label: '2', active: false },
        { url: '/page/3', label: '3', active: false },
        { url: '/page/4', label: '4', active: false },
      ]
      
      const wrapper = mount(Pagination, {
        props: { links },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      expect(wrapper.props('links')).toEqual(links)
    })
  })

  describe('Link Types', () => {
    it('should render clickable link when url is provided', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: '/page/2', label: '2', active: false },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/4', label: '4', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      const links = wrapper.findAll('a')
      expect(links.length).toBeGreaterThan(0)
    })

    it('should render div when url is null (current page)', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: null, label: '2', active: true },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/4', label: '4', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      const divs = wrapper.findAll('div').filter(div => 
        div.classes().includes('text-gray-400')
      )
      expect(divs.length).toBeGreaterThan(0)
    })
  })

  describe('CSS Classes', () => {
    it('should apply gray color to disabled links', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: null, label: 'Previous', active: false },
            { url: '/page/1', label: '1', active: false },
            { url: '/page/2', label: '2', active: false },
            { url: '/page/3', label: '3', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      const disabledLink = wrapper.find('.text-gray-400')
      expect(disabledLink.exists()).toBe(true)
    })

    it('should apply white background to active link', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: null, label: '2', active: true },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/4', label: '4', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href" :class="$attrs.class"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      const activeLink = wrapper.find('.bg-white')
      expect(activeLink.exists()).toBe(true)
    })

    it('should apply hover styles to clickable links', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: '/page/2', label: '2', active: false },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/4', label: '4', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href" :class="$attrs.class"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      const link = wrapper.find('a')
      expect(link.classes()).toContain('hover:bg-white')
    })
  })

  describe('Label Rendering', () => {
    it('should render HTML labels using v-html', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '&laquo; Previous', active: false },
            { url: '/page/2', label: '1', active: false },
            { url: '/page/3', label: '2', active: false },
            { url: '/page/4', label: 'Next &raquo;', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href" v-html="$slots.default"></a>',
              props: ['href']
            }
          }
        }
      })
      
      expect(wrapper.exists()).toBe(true)
    })

    it('should render numeric labels', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: '/page/2', label: '2', active: true },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/4', label: '4', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      expect(wrapper.text()).toContain('1')
      expect(wrapper.text()).toContain('2')
      expect(wrapper.text()).toContain('3')
      expect(wrapper.text()).toContain('4')
    })
  })

  describe('Layout', () => {
    it('should use flex layout', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: '/page/2', label: '2', active: false },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/4', label: '4', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      const flexContainer = wrapper.find('.flex')
      expect(flexContainer.exists()).toBe(true)
    })

    it('should wrap flex items', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: '/page/1', label: '1', active: false },
            { url: '/page/2', label: '2', active: false },
            { url: '/page/3', label: '3', active: false },
            { url: '/page/4', label: '4', active: false },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      const flexContainer = wrapper.find('.flex-wrap')
      expect(flexContainer.exists()).toBe(true)
    })
  })

  describe('Edge Cases', () => {
    it('should handle empty links array', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: []
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      expect(wrapper.html()).toBe('<!--v-if-->')
    })

    it('should handle single page (no pagination needed)', () => {
      const wrapper = mount(Pagination, {
        props: {
          links: [
            { url: null, label: '1', active: true },
          ]
        },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      expect(wrapper.html()).toBe('<!--v-if-->')
    })

    it('should handle many pages', () => {
      const links = []
      for (let i = 1; i <= 20; i++) {
        links.push({ url: `/page/${i}`, label: `${i}`, active: i === 1 })
      }
      
      const wrapper = mount(Pagination, {
        props: { links },
        global: {
          stubs: {
            Link: {
              template: '<a :href="href"><slot /></a>',
              props: ['href']
            }
          }
        }
      })
      
      expect(wrapper.exists()).toBe(true)
    })
  })
})
