import { beforeEach, describe, expect, it } from 'vitest'
import { LOCAL_WRITE, writeGate, writeKey } from '../src/Utils/writeGate'

/**
 * The property under test: whichever request was DISPATCHED last wins,
 * whatever order the network settles the responses in.
 */
describe('writeGate', () => {
  beforeEach(() => {
    writeGate.clear()
  })

  it('admits writes that arrive in dispatch order', () => {
    const first = writeGate.next(true)
    const second = writeGate.next(true)

    expect(writeGate.admit('media/1', first)).toBe(true)
    expect(writeGate.admit('media/1', second)).toBe(true)
  })

  it('rejects a write that settles after a later-dispatched one landed', () => {
    const first = writeGate.next(true)
    const second = writeGate.next(true)

    // The network reordered the responses: second lands first.
    expect(writeGate.admit('media/1', second)).toBe(true)
    expect(writeGate.admit('media/1', first)).toBe(false)
  })

  it('admits an equal sequence — two halves of one response share a number', () => {
    const stamp = writeGate.next(true)

    expect(writeGate.admit('media/1', stamp)).toBe(true)
    expect(writeGate.admit('media/1', stamp)).toBe(true)
  })

  it('gates per key: writes to different records never block each other', () => {
    const first = writeGate.next(true)
    const second = writeGate.next(true)

    expect(writeGate.admit('media/1', second)).toBe(true)
    // Older stamp, different record — unaffected.
    expect(writeGate.admit('media/2', first)).toBe(true)
  })

  it('does not record a non-recording stamp, so it cannot gate out a later save', () => {
    const read = writeGate.next(false)
    const save = writeGate.next(true)

    // The read is admitted but records nothing…
    expect(writeGate.admit('media/1', read)).toBe(true)
    // …so the save still lands afterwards instead of being gated out.
    expect(writeGate.admit('media/1', save)).toBe(true)

    // And a non-recording probe with the read's old number now reports stale.
    expect(writeGate.admit('media/1', { sequence: read.sequence, record: false })).toBe(false)
  })

  it('always admits LOCAL_WRITE and never records it', () => {
    const stamp = writeGate.next(true)
    writeGate.admit('media/1', stamp)

    expect(writeGate.admit('media/1', LOCAL_WRITE)).toBe(true)
    // The recorded sequence is unchanged: the old stamp still passes (equal).
    expect(writeGate.admit('media/1', stamp)).toBe(true)
  })

  it('seal rejects every write in flight, then admits newly dispatched ones', () => {
    const inFlight = writeGate.next(true)

    writeGate.seal('media/1')

    // Dispatched before the seal — dead data, must not resurrect the record.
    expect(writeGate.admit('media/1', inFlight)).toBe(false)
    // Dispatched after the seal — a genuinely new write, admitted.
    expect(writeGate.admit('media/1', writeGate.next(true))).toBe(true)
  })

  it('keeps sequences monotonic across clear-free lifetimes so keys cannot collide', () => {
    const a = writeGate.next(true)
    const b = writeGate.next(true)
    const c = writeGate.next(true)

    expect(a.sequence).toBeLessThan(b.sequence)
    expect(b.sequence).toBeLessThan(c.sequence)
  })
})

describe('writeKey', () => {
  it('composes resource, id and optional field', () => {
    expect(writeKey('media', 42)).toBe('media/42')
    expect(writeKey('media', '42', 'is_favorite')).toBe('media/42/is_favorite')
  })

  it('trims so a padded id cannot mint a second identity for one record', () => {
    expect(writeKey(' media ', ' 42 ')).toBe('media/42')
  })
})
