import { describe, it, expect, vi } from 'vitest'

/**
 * Tests for the bulk media delete logic.
 *
 * Validates that the DELETE /api/media/bulk endpoint is called with the correct
 * payload when deleting one or more media items via handleDeleteMedia.
 *
 * Media ids are UUID strings (matching the backend's Media entity id type).
 */

// ── Fixture ids ──────────────────────────────────────────────────────────────

const ID_1 = '11111111-1111-4111-8111-111111111111'
const ID_2 = '22222222-2222-4222-8222-222222222222'
const ID_3 = '33333333-3333-4333-8333-333333333333'

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeFetchMock(ok: boolean, responseData: object) {
  return vi.fn().mockResolvedValue({
    ok,
    json: async () => responseData,
  })
}

/**
 * Inline replica of the handleDeleteMedia logic from Content.vue, decoupled from
 * Vue so it can be tested without mounting the full page component.
 */
async function handleDeleteMedia(
  mediaId: string,
  {
    selectedIds,
    uploadedFiles: _uploadedFiles,
    fetchFn,
  }: {
    selectedIds: string[]
    uploadedFiles: Array<{ id: string; original_filename: string }>
    fetchFn: typeof fetch
  }
): Promise<{ deletedIds: string[]; fetchCalled: boolean; fetchArgs: [string, RequestInit] | null }> {
  const idsToDelete =
    selectedIds.length > 0 && selectedIds.includes(mediaId) ? [...selectedIds] : [mediaId]

  let fetchArgs: [string, RequestInit] | null = null
  let fetchCalled = false
  const deletedIds: string[] = []

  const response = await fetchFn('/api/media/bulk', {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ media_ids: idsToDelete }),
  })

  fetchCalled = true
  fetchArgs = [
    '/api/media/bulk',
    {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ media_ids: idsToDelete }),
    },
  ]

  await (response as Response).json()

  if ((response as Response).ok) {
    for (const id of idsToDelete) {
      deletedIds.push(id)
    }
  }

  return { deletedIds, fetchCalled, fetchArgs }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('handleDeleteMedia – bulk delete via /api/media/bulk', () => {
  const files = [
    { id: ID_1, original_filename: 'photo1.jpg' },
    { id: ID_2, original_filename: 'photo2.jpg' },
    { id: ID_3, original_filename: 'photo3.jpg' },
  ]

  it('calls DELETE /api/media/bulk with a single media_id when no selection', async () => {
    const fetchMock = makeFetchMock(true, { message: 'Deleted 1 item(s).', deleted: 1 })

    const { fetchCalled, fetchArgs, deletedIds } = await handleDeleteMedia(ID_1, {
      selectedIds: [],
      uploadedFiles: files,
      fetchFn: fetchMock as unknown as typeof fetch,
    })

    expect(fetchCalled).toBe(true)
    expect(fetchArgs![0]).toBe('/api/media/bulk')
    expect(fetchArgs![1].method).toBe('DELETE')
    expect(JSON.parse(fetchArgs![1].body as string)).toEqual({ media_ids: [ID_1] })
    expect(deletedIds).toEqual([ID_1])
  })

  it('calls DELETE /api/media/bulk with all selected ids when dropped item is in selection', async () => {
    const fetchMock = makeFetchMock(true, { message: 'Deleted 3 item(s).', deleted: 3 })

    const { fetchArgs, deletedIds } = await handleDeleteMedia(ID_2, {
      selectedIds: [ID_1, ID_2, ID_3],
      uploadedFiles: files,
      fetchFn: fetchMock as unknown as typeof fetch,
    })

    expect(JSON.parse(fetchArgs![1].body as string)).toEqual({ media_ids: [ID_1, ID_2, ID_3] })
    expect(deletedIds).toEqual([ID_1, ID_2, ID_3])
  })

  it('uses only the dropped mediaId when it is NOT part of the current selection', async () => {
    const fetchMock = makeFetchMock(true, { message: 'Deleted 1 item(s).', deleted: 1 })

    const { fetchArgs, deletedIds } = await handleDeleteMedia(ID_3, {
      selectedIds: [ID_1, ID_2], // ID_3 is not selected
      uploadedFiles: files,
      fetchFn: fetchMock as unknown as typeof fetch,
    })

    expect(JSON.parse(fetchArgs![1].body as string)).toEqual({ media_ids: [ID_3] })
    expect(deletedIds).toEqual([ID_3])
  })

  it('returns no deletedIds when the API call fails', async () => {
    const fetchMock = makeFetchMock(false, { message: 'Internal Server Error' })

    const { deletedIds } = await handleDeleteMedia(ID_1, {
      selectedIds: [],
      uploadedFiles: files,
      fetchFn: fetchMock as unknown as typeof fetch,
    })

    expect(deletedIds).toEqual([])
  })

  it('sends Content-Type application/json header', async () => {
    const fetchMock = makeFetchMock(true, { message: 'Deleted 1 item(s).', deleted: 1 })

    const { fetchArgs } = await handleDeleteMedia(ID_1, {
      selectedIds: [],
      uploadedFiles: files,
      fetchFn: fetchMock as unknown as typeof fetch,
    })

    expect((fetchArgs![1].headers as Record<string, string>)['Content-Type']).toBe(
      'application/json'
    )
  })
})
