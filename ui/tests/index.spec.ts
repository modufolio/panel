import { describe, it, expect } from 'vitest'
import { VERSION } from '../src/index'

describe('@modufolio/panel scaffold', () => {
  it('exposes a version marker', () => {
    expect(VERSION).toMatch(/^\d+\.\d+\.\d+$/)
  })
})
