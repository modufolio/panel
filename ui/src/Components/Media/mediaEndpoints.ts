/**
 * Backend paths the media picker calls, relative to the panel base URL.
 *
 * They are configurable because the picker is part of the package while the
 * endpoints serving it belong to the host application: without this, every
 * consumer would have to expose these exact paths or the picker (and with it
 * image insertion in the block editor) fails at runtime with no clear cause.
 *
 * Set them through `createPanel({ media: { … } })`.
 */
export interface MediaEndpoints {
  /** Paginated media listing. Receives the picker's query string. */
  list: string
  /** Albums used to filter the listing. */
  albums: string
}

const defaults: MediaEndpoints = {
  list: '/api/media/picker',
  albums: '/api/media/picker/albums',
}

let endpoints: MediaEndpoints = { ...defaults }

export function setMediaEndpoints(next: Partial<MediaEndpoints> | undefined): void {
  endpoints = { ...defaults, ...(next ?? {}) }
}

export function getMediaEndpoints(): MediaEndpoints {
  return endpoints
}
