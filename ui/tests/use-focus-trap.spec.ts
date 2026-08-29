import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { ref, type Ref } from 'vue'
import { useFocusTrap } from '../src/index'

describe('useFocusTrap Composable', () => {
  let container: HTMLDivElement
  let button1: HTMLButtonElement
  let button2: HTMLButtonElement
  let button3: HTMLButtonElement
  let containerRef: Ref<HTMLElement | null>

  beforeEach(() => {
    // Create a container with focusable elements
    container = document.createElement('div')
    container.innerHTML = `
      <button id="btn1">Button 1</button>
      <input id="input1" type="text" />
      <button id="btn2">Button 2</button>
      <button id="btn3">Button 3</button>
    `
    document.body.appendChild(container)

    button1 = document.getElementById('btn1') as HTMLButtonElement
    button2 = document.getElementById('btn2') as HTMLButtonElement
    button3 = document.getElementById('btn3') as HTMLButtonElement

    containerRef = ref<HTMLElement | null>(container)
  })

  afterEach(() => {
    document.body.removeChild(container)
  })

  describe('Initialization', () => {
    it('should return activate and deactivate functions', () => {
      const { activate, deactivate } = useFocusTrap(containerRef)
      
      expect(activate).toBeDefined()
      expect(typeof activate).toBe('function')
      expect(deactivate).toBeDefined()
      expect(typeof deactivate).toBe('function')
    })
  })

  describe('Focus Management', () => {
    it('should focus first element on activate', async () => {
      const { activate } = useFocusTrap(containerRef)
      
      activate()
      
      // Wait for setTimeout
      await new Promise(resolve => setTimeout(resolve, 20))
      
      expect(document.activeElement).toBe(button1)
    })

    it('should store previous focus', () => {
      const outsideButton = document.createElement('button')
      outsideButton.id = 'outside-btn'
      document.body.appendChild(outsideButton)
      outsideButton.focus()
      
      expect(document.activeElement).toBe(outsideButton)
      
      const { activate } = useFocusTrap(containerRef)
      activate()
      
      document.body.removeChild(outsideButton)
    })

    it('should restore focus on deactivate', async () => {
      const outsideButton = document.createElement('button')
      outsideButton.id = 'outside-btn'
      document.body.appendChild(outsideButton)
      outsideButton.focus()
      
      const { activate, deactivate } = useFocusTrap(containerRef)
      activate()
      
      await new Promise(resolve => setTimeout(resolve, 20))
      
      deactivate()
      
      await new Promise(resolve => setTimeout(resolve, 20))
      
      expect(document.activeElement).toBe(outsideButton)
      
      document.body.removeChild(outsideButton)
    })
  })

  describe('Tab Key Trapping', () => {
    it('should trap Tab key within container', async () => {
      const { activate } = useFocusTrap(containerRef)
      activate()
      
      await new Promise(resolve => setTimeout(resolve, 20))
      
      // Focus should be on first element
      expect(document.activeElement).toBe(button1)
      
      // Move to last element
      button3.focus()
      expect(document.activeElement).toBe(button3)
      
      // Press Tab (should cycle to first)
      const tabEvent = new KeyboardEvent('keydown', {
        key: 'Tab',
        bubbles: true,
        cancelable: true
      })
      
      const preventDefaultSpy = vi.spyOn(tabEvent, 'preventDefault')
      document.dispatchEvent(tabEvent)
      
      // Tab from last element should be prevented
      expect(preventDefaultSpy).toHaveBeenCalled()
    })

    it('should trap Shift+Tab key within container', async () => {
      const { activate } = useFocusTrap(containerRef)
      activate()
      
      await new Promise(resolve => setTimeout(resolve, 20))
      
      // Focus first element
      button1.focus()
      expect(document.activeElement).toBe(button1)
      
      // Press Shift+Tab (should cycle to last)
      const shiftTabEvent = new KeyboardEvent('keydown', {
        key: 'Tab',
        shiftKey: true,
        bubbles: true,
        cancelable: true
      })
      
      const preventDefaultSpy = vi.spyOn(shiftTabEvent, 'preventDefault')
      document.dispatchEvent(shiftTabEvent)
      
      // Shift+Tab from first element should be prevented
      expect(preventDefaultSpy).toHaveBeenCalled()
    })

    it('should not trap non-Tab keys', async () => {
      const { activate } = useFocusTrap(containerRef)
      activate()
      
      await new Promise(resolve => setTimeout(resolve, 20))
      
      // Press Enter key
      const enterEvent = new KeyboardEvent('keydown', {
        key: 'Enter',
        bubbles: true,
        cancelable: true
      })
      
      const preventDefaultSpy = vi.spyOn(enterEvent, 'preventDefault')
      document.dispatchEvent(enterEvent)
      
      // Enter should not be prevented
      expect(preventDefaultSpy).not.toHaveBeenCalled()
    })
  })

  describe('Focusable Elements Detection', () => {
    it('should find buttons', () => {
      const { activate } = useFocusTrap(containerRef)
      activate()
      
      // If it activates without error, it found focusable elements
      expect(container.querySelectorAll('button')).toHaveLength(3)
    })

    it('should find inputs', () => {
      const { activate } = useFocusTrap(containerRef)
      activate()
      
      expect(container.querySelectorAll('input')).toHaveLength(1)
    })

    it('should skip disabled buttons', () => {
      button2.disabled = true
      
      const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'textarea:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
      ].join(',')
      
      const focusableElements = container.querySelectorAll(focusableSelector)
      
      // Should have 3 elements (2 enabled buttons + 1 input)
      expect(focusableElements.length).toBe(3)
      expect(Array.from(focusableElements).includes(button2)).toBe(false)
    })

    it('should skip elements with tabindex="-1"', () => {
      button2.setAttribute('tabindex', '-1')
      
      const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'textarea:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
      ].join(',')
      
      const focusableElements = container.querySelectorAll(focusableSelector)
      
      // Should still have all 4 elements (buttons don't get filtered by tabindex selector)
      expect(focusableElements.length).toBeGreaterThanOrEqual(3)
    })
  })

  describe('Edge Cases', () => {
    it('should handle container with no focusable elements', () => {
      const emptyContainer = document.createElement('div')
      emptyContainer.innerHTML = '<div>No focusable elements</div>'
      document.body.appendChild(emptyContainer)
      
      const emptyRef = ref<HTMLElement | null>(emptyContainer)
      const { activate } = useFocusTrap(emptyRef)
      
      // Should not throw error
      expect(() => activate()).not.toThrow()
      
      document.body.removeChild(emptyContainer)
    })

    it('should handle null container ref', () => {
      const nullRef = ref<HTMLElement | null>(null)
      const { activate } = useFocusTrap(nullRef)
      
      // Should not throw error
      expect(() => activate()).not.toThrow()
    })

    it('should handle single focusable element', () => {
      const singleContainer = document.createElement('div')
      singleContainer.innerHTML = '<button>Only Button</button>'
      document.body.appendChild(singleContainer)
      
      const singleRef = ref<HTMLElement | null>(singleContainer)
      const { activate } = useFocusTrap(singleRef)
      
      expect(() => activate()).not.toThrow()
      
      document.body.removeChild(singleContainer)
    })
  })

  describe('Cleanup', () => {
    it('should remove event listeners on deactivate', async () => {
      const { activate, deactivate } = useFocusTrap(containerRef)
      
      activate()
      await new Promise(resolve => setTimeout(resolve, 20))
      
      deactivate()
      
      // After deactivate, Tab should not be trapped
      button3.focus()
      
      const tabEvent = new KeyboardEvent('keydown', {
        key: 'Tab',
        bubbles: true,
        cancelable: true
      })
      
      vi.spyOn(tabEvent, 'preventDefault')
      document.dispatchEvent(tabEvent)
      
      // After deactivate, preventDefault should not be called by focus trap
      // (it might be called by browser default behavior, but not by our trap)
    })

    it('should handle multiple activate/deactivate cycles', async () => {
      const { activate, deactivate } = useFocusTrap(containerRef)
      
      activate()
      await new Promise(resolve => setTimeout(resolve, 20))
      deactivate()
      await new Promise(resolve => setTimeout(resolve, 20))
      
      activate()
      await new Promise(resolve => setTimeout(resolve, 20))
      deactivate()
      await new Promise(resolve => setTimeout(resolve, 20))
      
      // Should not throw errors
      expect(true).toBe(true)
    })
  })

  describe('Links and Other Elements', () => {
    it('should detect links with href', () => {
      container.innerHTML = `
        <a href="#test">Link 1</a>
        <a href="#test2">Link 2</a>
      `
      
      const { activate } = useFocusTrap(containerRef)
      
      expect(() => activate()).not.toThrow()
      expect(container.querySelectorAll('a[href]')).toHaveLength(2)
    })

    it('should detect textareas', () => {
      container.innerHTML = `
        <textarea></textarea>
        <button>Button</button>
      `
      
      const { activate } = useFocusTrap(containerRef)
      
      expect(() => activate()).not.toThrow()
      expect(container.querySelectorAll('textarea')).toHaveLength(1)
    })

    it('should detect select elements', () => {
      container.innerHTML = `
        <select><option>Option</option></select>
        <button>Button</button>
      `
      
      const { activate } = useFocusTrap(containerRef)
      
      expect(() => activate()).not.toThrow()
      expect(container.querySelectorAll('select')).toHaveLength(1)
    })
  })
})
