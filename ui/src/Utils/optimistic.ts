import { writeGate } from './writeGate'

/**
 * Run an optimistic mutation: apply the local change immediately, fire the
 * request, and roll back if it rejects. This replaces the hand-rolled
 * snapshot -> mutate -> catch/rollback pattern duplicated across the media
 * mutation composables.
 *
 *   const ok = await optimistic(
 *     () => {
 *       const previous = file.is_favorite
 *       file.is_favorite = !previous
 *       return () => { file.is_favorite = previous }   // rollback
 *     },
 *     () => apiFetch(panelUrl(`/api/media/${file.id}`), { method: 'PATCH', body: {...} }),
 *     writeKey('media', file.id, 'is_favorite'),
 *   )
 *
 * `apply` performs the optimistic change and returns a rollback thunk.
 * Resolves true on success, false if the request failed (rolled back).
 *
 * The `key` orders overlapping mutations of the same state: without it, two
 * quick toggles interleave so that the FIRST one failing rolls state back to
 * before either ran, erasing the second's applied change. With a key, each
 * application is recorded on the writeGate at dispatch, and a failure only
 * rolls back while its own write is still the newest for that key — a stale
 * failure keeps its hands off state a later mutation now owns. (When every
 * overlapping write fails, the newest rollback restores the previous
 * optimistic value rather than the original — the older writes' truth is
 * unknowable locally at that point, and their toasts already fired.)
 *
 * `key` is optional only so unrelated one-shot mutations don't have to mint
 * one; any state that can be mutated twice concurrently should pass it.
 */
export async function optimistic(
  apply: () => () => void,
  request: () => Promise<unknown>,
  key?: string,
): Promise<boolean> {
  // Sequence taken at dispatch, and the optimistic application recorded as a
  // write immediately — a later call must outrank this one from the moment
  // its change is visible, not from when its request settles.
  const stamp = key === undefined ? null : writeGate.next(true)

  if (key !== undefined && stamp !== null) {
    writeGate.admit(key, stamp)
  }

  const rollback = apply()

  try {
    await request()

    return true
  } catch {
    // A non-recording stamp asks "is my write still the newest?" without
    // moving the gate. Older than the newest means a later mutation owns
    // this state now, and rolling back would destroy its applied change.
    const mayRollBack =
      key === undefined ||
      stamp === null ||
      writeGate.admit(key, { sequence: stamp.sequence, record: false })

    if (mayRollBack) {
      rollback()
    }

    return false
  }
}
