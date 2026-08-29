export interface TagsChangedPayload {
  action?: 'attach' | 'detach'
  tagId?: string | number
}

/**
 * Whether a `tags-changed` event (emitted by MediaInspectorContainer after
 * any tag attach/detach, single-media or bulk) should trigger a refetch on
 * a page filtered to one specific tag (e.g. Media/Tags.vue).
 *
 * Only a detach of THAT exact tag can drop the current item out of such a
 * view. Attaching a tag must never trigger it, and neither must detaching
 * some other, unrelated tag from the same item — either would clear the
 * current selection and kick the user out of whatever they're still
 * editing, for no reason (this broke twice before landing on this rule).
 */
export function shouldRefetchOnTagsChanged(
  payload: TagsChangedPayload | undefined,
  currentTagId: string | number,
): boolean {
  return payload?.action === 'detach' && payload.tagId === currentTagId
}
