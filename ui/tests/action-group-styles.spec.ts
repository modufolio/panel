import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * The menu's legibility rules live in CSS, which happy-dom does not compute,
 * so the stylesheet is checked as text: the specific values here are the
 * fixes for a menu whose focus ring was clipped into two stripes, whose
 * active row was invisible in dark, and whose icons failed 3:1.
 */
const css = readFileSync(resolve(__dirname, '../styles/components.css'), 'utf8')

const block = (selector: string): string => {
  const start = css.indexOf(selector)
  expect(start, `${selector} is declared`).toBeGreaterThan(-1)
  return css.slice(start, css.indexOf('}', start))
}

describe('action group menu styles', () => {
  it('replaces the offset focus ring with an inset, unclippable one', () => {
    const focus = block('.ui-action-group-item:focus-visible')

    expect(focus).toMatch(/outline:\s*none/)
    expect(focus).toMatch(/box-shadow:\s*inset/)
    expect(focus).toMatch(/background-color:\s*var\(--item-hover-bg\)/)
  })

  it('lifts the active row and icon above the faint defaults', () => {
    const base = block(':where(.ui-action-group-item)')

    expect(base).toMatch(/--item-hover-bg:\s*var\(--pressed\)/)
    expect(base).toMatch(/--item-icon:\s*var\(--ink-2\)/)
  })

  it('colours the whole danger row with the softened on-surface red', () => {
    const danger = block(":where(.ui-action-group-item[data-color='danger'])")

    expect(danger).toMatch(/--item-fg:\s*var\(--danger-on-surface\)/)
    expect(danger).toMatch(/--item-icon:\s*var\(--danger-on-surface\)/)
    expect(danger).toMatch(/--item-hover-bg:\s*var\(--danger-surface\)/)
  })

  it('draws the separator as a hairline', () => {
    expect(block('.ui-action-group-separator')).toMatch(/border-top:\s*1px solid var\(--hairline\)/)
  })
})
