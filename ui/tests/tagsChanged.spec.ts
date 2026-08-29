import { describe, it, expect } from 'vitest'
import { shouldRefetchOnTagsChanged } from '../src/index'

// Regression coverage for a bug that broke twice on Media/Tags.vue:
//   1st fix: any tags-changed event (including 'attach') cleared the
//     selection, blanking the inspector mid-edit for a single item.
//   2nd fix: detaching ANY tag (not just the page's own) still cleared it —
//     e.g. removing an unrelated second tag from an item on the "bags" page
//     kicked the user out of editing even though the item stayed tagged
//     "bags" and never left the view.
// Personas from that trace, used as the test names below: Priya (attach,
// single), Sam (bulk-detach the page's own tag), Dana (attach while viewing
// the page's own tag), Eli (detach a DIFFERENT tag than the page's own).

describe('shouldRefetchOnTagsChanged', () => {
  const BAGS_TAG_ID = 'bags-uuid'

  it('Priya: attaching a tag never triggers a refetch', () => {
    expect(shouldRefetchOnTagsChanged({ action: 'attach', tagId: BAGS_TAG_ID }, BAGS_TAG_ID)).toBe(false)
  })

  it('Sam: detaching the page\'s own tag triggers a refetch', () => {
    expect(shouldRefetchOnTagsChanged({ action: 'detach', tagId: BAGS_TAG_ID }, BAGS_TAG_ID)).toBe(true)
  })

  it('Dana: attaching a different tag while viewing the page\'s own tag never triggers a refetch', () => {
    expect(shouldRefetchOnTagsChanged({ action: 'attach', tagId: 'leather-uuid' }, BAGS_TAG_ID)).toBe(false)
  })

  it('Eli: detaching an unrelated tag (not the page\'s own) never triggers a refetch', () => {
    expect(shouldRefetchOnTagsChanged({ action: 'detach', tagId: 'leather-uuid' }, BAGS_TAG_ID)).toBe(false)
  })

  it('treats a missing payload as a no-op', () => {
    expect(shouldRefetchOnTagsChanged(undefined, BAGS_TAG_ID)).toBe(false)
  })

  it('treats a payload with no tagId as a no-op, even on detach', () => {
    expect(shouldRefetchOnTagsChanged({ action: 'detach' }, BAGS_TAG_ID)).toBe(false)
  })
})
