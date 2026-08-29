import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, config, enableAutoUnmount } from '@vue/test-utils'
import { Modal } from '../src/index'

describe('Modal Component', () => {
  let bodyOverflowOriginal: string

  // The scroll lock is reference counted across every open overlay, so a modal
  // left mounted by an earlier test would still be holding it here.
  enableAutoUnmount(afterEach)

  beforeEach(() => {
    bodyOverflowOriginal = document.body.style.overflow
    // Stub Teleport so modal content renders inline and is findable via wrapper.find()
    config.global = { stubs: { Teleport: true } } as any
  })

  afterEach(() => {
    document.body.style.overflow = bodyOverflowOriginal
    config.global = {} as any
  })

  describe('Rendering', () => {
    it('should not render when show is false', () => {
      const wrapper = mount(Modal, {
        props: {
          show: false
        }
      })

      expect(wrapper.find('.fixed').exists()).toBe(false)
    })

    it('should render when show is true', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      expect(wrapper.find('.fixed.inset-0').exists()).toBe(true)
    })

    it('should render slot content', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        },
        slots: {
          default: '<div class="modal-content">Test Content</div>'
        }
      })

      expect(wrapper.find('.modal-content').exists()).toBe(true)
      expect(wrapper.text()).toContain('Test Content')
    })

    it('should render header slot', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        },
        slots: {
          header: '<h2>Modal Title</h2>'
        }
      })

      expect(wrapper.find('h2').exists()).toBe(true)
      expect(wrapper.text()).toContain('Modal Title')
    })

    it('should render close button', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const closeButton = wrapper.find('button[type="button"]')
      expect(closeButton.exists()).toBe(true)
    })
  })

  describe('Props', () => {
    it('should default to show=false', () => {
      const wrapper = mount(Modal)

      expect(wrapper.props('show')).toBe(false)
    })

    it('should accept show=true', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      expect(wrapper.props('show')).toBe(true)
    })

    it('should accept maxWidth prop', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true,
          maxWidth: '4xl'
        }
      })

      expect(wrapper.props('maxWidth')).toBe('4xl')
    })

    it('should default maxWidth to 2xl', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      expect(wrapper.props('maxWidth')).toBe('2xl')
    })
  })

  describe('Events', () => {
    it('should emit close when close button clicked', async () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const closeButton = wrapper.find('button[type="button"]')
      await closeButton.trigger('click')

      expect(wrapper.emitted('close')).toBeTruthy()
      expect(wrapper.emitted('close')).toHaveLength(1)
    })

    it('should emit close when overlay clicked', async () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const overlay = wrapper.find('.bg-black.bg-opacity-50')
      await overlay.trigger('click')

      expect(wrapper.emitted('close')).toBeTruthy()
    })

    it('should emit close when container self-clicked', async () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      // Find the outer container with @click.self
      const container = wrapper.find('.fixed.inset-0.z-50')
      await container.trigger('click')

      expect(wrapper.emitted('close')).toBeTruthy()
    })

    it('should emit close multiple times', async () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const closeButton = wrapper.find('button[type="button"]')
      await closeButton.trigger('click')
      await closeButton.trigger('click')
      await closeButton.trigger('click')

      expect(wrapper.emitted('close')).toHaveLength(3)
    })
  })

  describe('Body Scroll Lock', () => {
    it('should lock body scroll when modal opens', async () => {
      const wrapper = mount(Modal, {
        props: {
          show: false
        }
      })

      expect(document.body.style.overflow).toBe('')

      await wrapper.setProps({ show: true })

      // Give time for the watcher to execute
      await new Promise(resolve => setTimeout(resolve, 10))

      expect(document.body.style.overflow).toBe('hidden')
    })

    it('should restore body scroll when modal closes', async () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      await new Promise(resolve => setTimeout(resolve, 10))
      expect(document.body.style.overflow).toBe('hidden')

      await wrapper.setProps({ show: false })
      await new Promise(resolve => setTimeout(resolve, 10))

      expect(document.body.style.overflow).toBe('')
    })
  })

  describe('Escape Key', () => {
    it('should close modal on Escape key', async () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      // Give time for event listener to attach
      await new Promise(resolve => setTimeout(resolve, 10))

      // Simulate Escape key press
      const escapeEvent = new KeyboardEvent('keydown', { key: 'Escape' })
      document.dispatchEvent(escapeEvent)

      expect(wrapper.emitted('close')).toBeTruthy()
    })

    it('should not close on other keys', async () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      await new Promise(resolve => setTimeout(resolve, 10))

      // Simulate Enter key press
      const enterEvent = new KeyboardEvent('keydown', { key: 'Enter' })
      document.dispatchEvent(enterEvent)

      expect(wrapper.emitted('close')).toBeFalsy()
    })
  })

  describe('Layout', () => {
    it('should have z-index of 50', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const container = wrapper.find('.z-50')
      expect(container.exists()).toBe(true)
    })

    it('should center modal vertically', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const flexContainer = wrapper.find('.flex.min-h-screen.items-center')
      expect(flexContainer.exists()).toBe(true)
    })

    it('should have max width of 2xl by default', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const modalContent = wrapper.find('.max-w-2xl')
      expect(modalContent.exists()).toBe(true)
    })

    it('should have rounded corners', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const modalContent = wrapper.find('.rounded-lg')
      expect(modalContent.exists()).toBe(true)
    })
  })

  describe('Styling', () => {
    it('should have overlay with opacity', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const overlay = wrapper.find('.bg-black.bg-opacity-50')
      expect(overlay.exists()).toBe(true)
    })

    it('should have white background for content', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const content = wrapper.find('.bg-white.px-6.py-4')
      expect(content.exists()).toBe(true)
    })

    it('should have header border', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const header = wrapper.find('.border-b.border-gray-200')
      expect(header.exists()).toBe(true)
    })
  })

  describe('Close Button', () => {
    it('should have correct type', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const button = wrapper.find('button')
      expect(button.attributes('type')).toBe('button')
    })

    it('should have close icon SVG', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const svg = wrapper.find('button svg')
      expect(svg.exists()).toBe(true)
    })

    it('should have hover styles', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const button = wrapper.find('button')
      expect(button.classes()).toContain('hover:text-gray-500')
    })
  })

  describe('Transitions', () => {
    it('should have transition classes on container', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      const transition = wrapper.findComponent({ name: 'Transition' })
      expect(transition.exists()).toBe(true)
    })
  })

  describe('Teleport', () => {
    it('should render content that would be teleported to body', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      // Modal content renders correctly (Teleport stubbed in tests for testability)
      expect(wrapper.find('.fixed.inset-0').exists()).toBe(true)
    })
  })

  describe('Edge Cases', () => {
    it('should handle rapid show/hide toggling', async () => {
      const wrapper = mount(Modal, {
        props: {
          show: false
        }
      })

      await wrapper.setProps({ show: true })
      await wrapper.setProps({ show: false })
      await wrapper.setProps({ show: true })
      await wrapper.setProps({ show: false })

      expect(wrapper.exists()).toBe(true)
    })

    it('should render without slots', () => {
      const wrapper = mount(Modal, {
        props: {
          show: true
        }
      })

      expect(wrapper.exists()).toBe(true)
    })
  })
})
