import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'

const handlers: Array<(event: any) => unknown> = []

vi.mock('@inertiajs/vue3', () => ({
  router: {
    on: (event: string, handler: (e: any) => unknown) => {
      if (event === 'before') handlers.push(handler)

      return () => {
        const index = handlers.indexOf(handler)
        if (index > -1) handlers.splice(index, 1)
      }
    },
  },
}))

import { useUnsavedChangesWarning } from '../src/Composables/useUnsavedChangesWarning'

/**
 * The guard between a dirty form and a navigation that would discard it —
 * Logout included, since Inertia treats it as an ordinary visit and so it
 * passes through this same `before` hook.
 */

let confirmed: boolean
let asked: string[]

beforeEach(() => {
  handlers.length = 0
  asked = []
  confirmed = false
  vi.stubGlobal('confirm', (message: string) => {
    asked.push(message)

    return confirmed
  })
})

afterEach(() => {
  vi.unstubAllGlobals()
})

/** Mount the composable, returning its handle. */
function mountGuard(form: { isDirty: boolean } | (() => boolean)) {
  let api!: ReturnType<typeof useUnsavedChangesWarning>

  const wrapper = mount(defineComponent({
    setup() {
      api = useUnsavedChangesWarning(form)

      return () => h('div')
    },
  }))

  return { wrapper, allowNextNavigation: () => api.allowNextNavigation() }
}

/** A `before` visit shaped like the one Inertia dispatches. */
function visit(detail: Record<string, unknown> = {}) {
  const event = {
    detail: { visit: detail },
    prevented: false,
    preventDefault() {
      this.prevented = true
    },
  }

  const allowed = handlers.map((handler) => handler(event)).every(Boolean)

  return { event, allowed }
}

describe('useUnsavedChangesWarning', () => {
  it('lets a navigation through when the form is clean', () => {
    mountGuard({ isDirty: false })

    const { allowed, event } = visit()

    expect(allowed).toBe(true)
    expect(event.prevented).toBe(false)
    expect(asked).toHaveLength(0)
  })

  it('prompts and blocks when the form is dirty and the user declines', () => {
    confirmed = false
    mountGuard({ isDirty: true })

    const { allowed, event } = visit()

    expect(asked).toEqual(['You have unsaved changes. Leave anyway?'])
    expect(allowed).toBe(false)
    expect(event.prevented).toBe(true)
  })

  it('lets the navigation proceed when the user accepts', () => {
    confirmed = true
    mountGuard({ isDirty: true })

    const { allowed, event } = visit()

    expect(allowed).toBe(true)
    expect(event.prevented).toBe(false)
  })

  it('reads a getter at event time, not at setup time', () => {
    // The drawer form passes a computed's value through a getter; it flips
    // long after the guard was created.
    let dirty = false
    mountGuard(() => dirty)

    expect(visit().allowed).toBe(true)

    dirty = true
    expect(visit().allowed).toBe(false)
  })

  it('allowNextNavigation skips exactly one prompt — the save itself', () => {
    const { allowNextNavigation } = mountGuard({ isDirty: true })

    allowNextNavigation()

    expect(visit().allowed).toBe(true)
    expect(asked).toHaveLength(0)

    // Only that one: the navigation after it is guarded again, so a save
    // that leaves the form dirty does not open a hole.
    expect(visit().allowed).toBe(false)
    expect(asked).toHaveLength(1)
  })

  it('never prompts for prefetch or deferred-props background fetches', () => {
    mountGuard({ isDirty: true })

    expect(visit({ prefetch: true }).allowed).toBe(true)
    expect(visit({ deferredProps: true }).allowed).toBe(true)
    expect(asked).toHaveLength(0)
  })

  it('stops guarding once the component is gone', () => {
    const { wrapper } = mountGuard({ isDirty: true })

    wrapper.unmount()

    expect(handlers).toHaveLength(0)
    expect(visit().allowed).toBe(true)
  })
})
