import { ref, computed, type Ref, type ComputedRef } from 'vue'
import { apiFetch } from '../../Utils/apiFetch'

const buildUrl = (endpoint: string, params: Record<string, unknown>): string => {
  const search = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null) return
    search.append(key, String(value))
  })
  const query = search.toString()
  return query ? `${endpoint}?${query}` : endpoint
}

export interface RelationshipConfig {
  endpoint?: string | null
  relationship?: string | null
  include?: string[]
  searchable?: boolean
  perPage?: number
}

export interface QueryOptions {
  page?: number
  perPage?: number
  filters?: Record<string, unknown>
  sort?: string | string[]
}

/**
 * Composable for handling relationship data fetching and management
 * Based on JsonApiQueryBuilder patterns
 */
export function useRelationship(config: RelationshipConfig = {}) {
  const {
    endpoint = null,
    relationship = null,
    include = [],
    searchable = true,
    perPage = 25,
  } = config

  const records: Ref<Record<string, unknown>[]> = ref([])
  const loading: Ref<boolean> = ref(false)
  const searchTerm: Ref<string> = ref('')
  const page: Ref<number> = ref(1)
  const totalRecords: Ref<number> = ref(0)
  const lastPage: Ref<number> = ref(1)

  /**
   * Build query parameters following JSON:API spec
   */
  const buildQueryParams = (options: QueryOptions = {}): Record<string, unknown> => {
    const params: Record<string, unknown> = {}

    // Include related resources
    if (include.length > 0) {
      params.include = include.join(',')
    }

    // Search filter
    if (searchTerm.value && searchable) {
      params['filter[search]'] = searchTerm.value
    }

    // Pagination
    params['page[number]'] = options.page || page.value
    params['page[size]'] = options.perPage || perPage

    // Custom filters
    if (options.filters) {
      Object.entries(options.filters).forEach(([key, value]) => {
        if (typeof value === 'object' && value !== null) {
          // Support for advanced filters like: { gt: 10, lt: 100 }
          Object.entries(value).forEach(([operator, operatorValue]) => {
            params[`filter[${key}][${operator}]`] = operatorValue
          })
        } else {
          params[`filter[${key}]`] = value
        }
      })
    }

    // Sorting
    if (options.sort) {
      params.sort = Array.isArray(options.sort)
        ? options.sort.join(',')
        : options.sort
    }

    return params
  }

  /**
   * Fetch related records from API
   */
  const fetchRecords = async (options: QueryOptions = {}): Promise<Record<string, unknown>> => {
    if (!endpoint) {
      console.warn('No endpoint provided for useRelationship')
      return {}
    }

    loading.value = true

    try {
      const params = buildQueryParams(options)
      // `?? {}` keeps the old parser's answer for an empty body: an endpoint
      // that replies 204 leaves the list empty rather than throwing here.
      const data = await apiFetch<Record<string, unknown> & {
        data?: Record<string, unknown>[]
        meta?: { total?: number; last_page?: number }
      } | null>(buildUrl(endpoint, params)) ?? {}

      // Handle JSON:API response format
      if (data.data) {
        records.value = data.data
      } else {
        records.value = data as unknown as Record<string, unknown>[]
      }

      // Handle pagination meta
      if (data.meta) {
        totalRecords.value = data.meta.total || 0
        lastPage.value = data.meta.last_page || 1
      }

      return data
    } catch (error) {
      console.error('Error fetching related records:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  /**
   * Search records
   */
  const search = async (term: string): Promise<void> => {
    searchTerm.value = term
    page.value = 1
    await fetchRecords()
  }

  /**
   * Go to specific page
   */
  const goToPage = async (pageNumber: number): Promise<void> => {
    page.value = pageNumber
    await fetchRecords()
  }

  /**
   * Attach a related record (for many-to-many)
   */
  const attach = async (resourceId: string | number, relatedId: string | number): Promise<boolean> => {
    try {
      await apiFetch(`${endpoint}/${resourceId}/relationships/${relationship}`, {
        method: 'POST',
        body: { data: { type: relationship, id: relatedId } },
      })

      return true
    } catch (error) {
      console.error('Error attaching relationship:', error)
      throw error
    }
  }

  /**
   * Detach a related record (for many-to-many)
   */
  const detach = async (resourceId: string | number, relatedId: string | number): Promise<boolean> => {
    try {
      await apiFetch(`${endpoint}/${resourceId}/relationships/${relationship}/${relatedId}`, {
        method: 'DELETE',
      })

      return true
    } catch (error) {
      console.error('Error detaching relationship:', error)
      throw error
    }
  }

  /**
   * Associate a related record (for belongs-to)
   */
  const associate = async (resourceId: string | number, relatedId: string | number): Promise<boolean> => {
    try {
      await apiFetch(`${endpoint}/${resourceId}`, {
        method: 'PATCH',
        body: { data: { type: relationship, id: relatedId } },
      })

      return true
    } catch (error) {
      console.error('Error associating relationship:', error)
      throw error
    }
  }

  /**
   * Dissociate a related record (for belongs-to)
   */
  const dissociate = async (resourceId: string | number): Promise<boolean> => {
    try {
      await apiFetch(`${endpoint}/${resourceId}`, {
        method: 'PATCH',
        body: { data: { type: relationship, id: null } },
      })

      return true
    } catch (error) {
      console.error('Error dissociating relationship:', error)
      throw error
    }
  }

  /**
   * Create a new related record
   */
  const create = async (resourceId: string | number, data: Record<string, unknown>): Promise<Record<string, unknown>> => {
    try {
      // `?? {}` keeps the old parser's answer for an empty body: this
      // resolves to the created record where the server sends one back.
      const body = await apiFetch<Record<string, unknown> | null>(`${endpoint}/${resourceId}/${relationship}`, {
        method: 'POST',
        body: { data: { type: relationship, attributes: data } },
      }) ?? {}

      // Refresh records after creation
      await fetchRecords()

      return body
    } catch (error) {
      console.error('Error creating related record:', error)
      throw error
    }
  }

  /**
   * Delete a related record
   */
  const deleteRecord = async (relatedId: string | number): Promise<boolean> => {
    try {
      await apiFetch(`${endpoint}/${relatedId}`, { method: 'DELETE' })

      // Refresh records after deletion
      await fetchRecords()

      return true
    } catch (error) {
      console.error('Error deleting related record:', error)
      throw error
    }
  }

  const hasRecords: ComputedRef<boolean> = computed(() => records.value.length > 0)
  const isEmpty: ComputedRef<boolean> = computed(() => !loading.value && records.value.length === 0)

  return {
    // State
    records,
    loading,
    searchTerm,
    page,
    totalRecords,
    lastPage,

    // Computed
    hasRecords,
    isEmpty,

    // Methods
    fetchRecords,
    search,
    goToPage,
    attach,
    detach,
    associate,
    dissociate,
    create,
    deleteRecord,
    buildQueryParams,
  }
}
