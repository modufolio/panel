/**
 * The stale-write gate: ordering for concurrent writes to the same record.
 *
 * Two concurrent writes to one record must leave local state on the newer
 * one, not on whichever response settled last. The order that matters is the
 * order the requests were DISPATCHED in: a request dispatched later carries
 * the newer intent, whatever the network does with it afterwards.
 *
 * So every write takes a sequence number when it is dispatched, and presents
 * that number to `admit` before it lands. A write whose sequence is older
 * than the newest already applied for that key is rejected. The record is
 * per key, so writes to different records never gate each other.
 *
 * A sequence counter, not a timestamp: clocks move, and two responses can
 * share a millisecond. The counter is store-wide and only ever incremented —
 * a per-key counter restarting at 0 would let a number minted for one key
 * match a later slot's, which is how a deleted record comes back to life.
 * Comparison is still per key, which is what "per-key sequence" buys.
 *
 * Design ported from frappe-ui's writeGate (src/data-fetching/writeGate.ts),
 * which solves the same bug for its doc/list stores. Here the keys are
 * caller-composed strings — `writeKey('media', file.id)`, optionally with a
 * field suffix — because the panel has no doctype registry to derive them.
 */

/**
 * What a dispatched write presents to the gate: the sequence number of the
 * request that produced it, and whether the write may RECORD that number.
 *
 * Only a mutating request records. A later-dispatched request that failed
 * wrote nothing on the server, so it must not make an older success stale;
 * and a read is not an ordered write at all. A non-recording stamp is
 * admitted on its sequence and records nothing — which also makes it the
 * way to ask "is my write still the newest?" without moving the gate.
 */
export type DispatchStamp = { sequence: number; record: boolean }

/**
 * A write that did not come from a dispatched request — seeding state in a
 * test, a value that never went over the wire. There is no order to compare
 * it against, so it is always admitted and records nothing.
 *
 * A value a caller has to name, not an argument they can omit: the
 * un-ordered case is a decision at the call site rather than the silent
 * default of a forgotten parameter.
 */
export const LOCAL_WRITE = Symbol('local write')

export type WriteStamp = DispatchStamp | typeof LOCAL_WRITE

class WriteGate {
  // Only ever incremented; shared by every key. See the module comment for
  // why this is not per key and not a clock.
  private counter = 0

  // The newest sequence applied to each key.
  private applied = new Map<string, number>()

  /**
   * Take the next sequence number. Called when a request is dispatched — not
   * when it settles — so the number reflects the order the writes were
   * intended in.
   */
  next(record: boolean): DispatchStamp {
    return { sequence: ++this.counter, record }
  }

  /**
   * Whether this write may land on this key.
   *
   * Asking is booking: an admitted recording write becomes the newest for
   * the key, so a later call carrying an older sequence is rejected. An
   * equal sequence passes — the two halves of one response share a number.
   */
  admit(key: string, stamp: WriteStamp): boolean {
    if (stamp === LOCAL_WRITE) return true
    if (stamp.sequence < (this.applied.get(key) ?? 0)) return false
    if (stamp.record) this.applied.set(key, stamp.sequence)
    return true
  }

  /**
   * The record is gone on the server: reject every write in flight for it.
   *
   * Stamped with a FRESH number rather than the delete's own, because a
   * delete becomes true when it SETTLES. Any write still in flight at that
   * moment must have committed before the delete to have succeeded, so its
   * response is dead data and must not re-create the record. Anything
   * dispatched after this point takes a higher number and is admitted again.
   */
  seal(key: string) {
    this.applied.set(key, ++this.counter)
  }

  clear() {
    this.applied.clear()
  }
}

export const writeGate = new WriteGate()

/** One spelling for gate keys, so call sites agree on what "the same record" means. */
export function writeKey(resource: string, id: string | number, field?: string): string {
  const base = `${String(resource).trim()}/${String(id).trim()}`

  return field ? `${base}/${field}` : base
}
