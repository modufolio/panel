/**
 * Composable for handling inline table editing with backend integration
 */

import { router } from '@inertiajs/vue3'

export interface Page {
  props: Record<string, unknown>
}

export type FieldUpdateValue = string | number | boolean | null | undefined
export type RecordData = Record<string, FieldUpdateValue>

export interface InlineEditOptions {
  endpoint?: string | null
  method?: 'get' | 'post' | 'patch' | 'delete'
  onSuccess?: ((page: Page, record: TableRecord, field: string | RecordData, value?: FieldUpdateValue) => void) | null
  onError?: ((errors: Record<string, unknown>, record: TableRecord, field: string | RecordData, value?: FieldUpdateValue) => void) | null
  preserveScroll?: boolean
  preserveState?: boolean | 'errors'
  only?: string[]
  invalidateCacheTags?: string[]
}

export interface TableRecord {
  id: number | string
  [key: string]: FieldUpdateValue | FieldUpdateValue[]
}

export function useInlineEdit(options: InlineEditOptions = {}) {
  const {
    endpoint = null,
    method = 'patch',
    onSuccess = null,
    onError = null,
    preserveScroll = true,
    preserveState = false,
    only = undefined,
    invalidateCacheTags = [],
  } = options

  /**
   * Update a single field on a record
   */
  async function updateField(record: TableRecord, field: string, value: FieldUpdateValue): Promise<Page> {
    if (!record.id) {
      console.error('Record must have an id property')
      return Promise.reject(new Error('Record must have an id'))
    }

    const url = endpoint
      ? `${endpoint}/${record.id}`
      : `/api/${getResourceName()}/${record.id}`

    const data = { [field]: value }

    return new Promise((resolve, reject) => {
      router[method](
        url,
        data,
        {
          preserveScroll,
          preserveState,
          ...(only ? { only } : {}),
          invalidateCacheTags,
          onSuccess: (page) => {
            if (onSuccess) {
              onSuccess(page, record, field, value)
            }
            resolve(page)
          },
          onError: (errors) => {
            if (onError) {
              onError(errors, record, field, value)
            }
            reject(errors)
          },
        }
      )
    })
  }

  /**
   * Update multiple fields on a record
   */
  async function updateRecord(record: TableRecord, data: RecordData): Promise<Page> {
    if (!record.id) {
      console.error('Record must have an id property')
      return Promise.reject(new Error('Record must have an id'))
    }

    const url = endpoint
      ? `${endpoint}/${record.id}`
      : `/api/${getResourceName()}/${record.id}`

    return new Promise((resolve, reject) => {
      router[method](
        url,
        data,
        {
          preserveScroll,
          preserveState,
          ...(only ? { only } : {}),
          invalidateCacheTags,
          onSuccess: (page) => {
            if (onSuccess) {
              onSuccess(page, record, data)
            }
            resolve(page)
          },
          onError: (errors) => {
            if (onError) {
              onError(errors, record, data)
            }
            reject(errors)
          },
        }
      )
    })
  }

  /**
   * Helper to get resource name from current route
   */
  function getResourceName(): string {
    const path = window.location.pathname
    const segments = path.split('/').filter(Boolean)
    return segments[0] || 'resources'
  }

  /**
   * Create an update handler for inline edit columns
   */
  function createUpdateHandler(customEndpoint: string | null = null) {
    return async (record: TableRecord, column: string, value: FieldUpdateValue) => {
      const url = customEndpoint || endpoint
      const opts = { ...options, endpoint: url }
      const editor = useInlineEdit(opts)
      return editor.updateField(record, column, value)
    }
  }

  return {
    updateField,
    updateRecord,
    createUpdateHandler,
  }
}
