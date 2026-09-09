import { ref, watch, onScopeDispose } from 'vue'
import type * as tus from 'tus-js-client'
import { getCsrfToken } from '../Utils/csrf'

/**
 * Generic, domain-free TUS resumable-upload queue. Knows nothing about media
 * libraries — apps hook domain behavior via the options callbacks, and layer
 * their own listing (a media library, a document list) on top.
 *
 * `tus-js-client` is an optional peer dependency, loaded with a dynamic
 * import the first time an upload actually starts — an app that never
 * uploads never pays for it, and one that does not depend on it can still
 * import everything else from the package.
 */

export type UploadStatus = 'pending' | 'uploading' | 'paused' | 'completed' | 'error'

export interface UploadItem {
  id: number
  file: File
  progress: number
  status: UploadStatus
  tusUpload: tus.Upload | null
  error: string | null
  onComplete: ((file: unknown) => void) | null
  createdAt: number
  /**
   * Set by a host that reinstates an interrupted upload across a reload: the
   * item is shown as needing the file re-picked, not as in flight, and
   * `fileName` stands in for the `File` the browser will not hand back. The
   * queue itself sets neither — an upload it resumes is a normal one.
   */
  isRestored?: boolean
  fileName?: string
  _clearTimer?: ReturnType<typeof setTimeout>
}

export interface AddFilesOptions {
  onComplete?: (file: unknown) => void
  onRejected?: (rejected: File[]) => void
}

export interface UseTusUploadQueueOptions {
  /** TUS server endpoint (required — the queue never assumes a mount path). */
  endpoint: string
  /** Extra request headers; defaults to attaching the panel CSRF token. */
  headers?: () => Record<string, string>
  /** Chunk size in bytes (default 5 MB). */
  chunkSize?: number
  /** MIME types to reject client-side (defaults mirror the server blocklist). */
  blockedMimeTypes?: Iterable<string>
  /** Called once per successfully uploaded file (e.g. invalidate counts). */
  onUploaded?: (tusFilename: string | null, item: UploadItem) => void
  /**
   * Resolve the value passed to a per-file `onComplete` callback. Default:
   * `{ filename }`. Apps can look the record up server-side here.
   */
  resolveUploadedFile?: (tusFilename: string | null) => Promise<unknown> | unknown
  /** Called after a file finished and its callbacks ran (debounce reloads here). */
  onSettled?: () => void
}

const DEFAULT_BLOCKED_MIME_TYPES = [
  'text/html',
  'text/javascript',
  'application/javascript',
  'application/x-php',
  'text/x-php',
  'application/x-httpd-php',
  'image/svg+xml',
]

export function useTusUploadQueue(options: UseTusUploadQueueOptions) {
  const tusEndpoint = options.endpoint
  const blockedMimeTypes = new Set(options.blockedMimeTypes ?? DEFAULT_BLOCKED_MIME_TYPES)
  const buildHeaders = options.headers ?? (() => ({ 'X-CSRF-Token': getCsrfToken() ?? '' }))

  const uploads = ref<UploadItem[]>([])
  let uploadCounter = 0

  // Helper function to update upload progress (forces reactivity)
  const updateUploadProgress = (uploadId: number, progress: number) => {
    const index = uploads.value.findIndex(u => u.id === uploadId)
    if (index !== -1) {
      uploads.value[index] = {
        ...uploads.value[index],
        progress: Math.min(100, progress),
      }
    }
  }

  // Start TUS upload
  const startUpload = async (uploadItem: UploadItem) => {
    uploadItem.status = 'uploading'

    const { Upload } = await import('tus-js-client')

    const upload = new Upload(uploadItem.file, {
      endpoint: tusEndpoint,
      headers: buildHeaders(),
      retryDelays: [0, 3000, 5000, 10000, 20000],
      metadata: {
        filename: uploadItem.file.name,
        filetype: uploadItem.file.type,
      },
      chunkSize: options.chunkSize ?? 5 * 1024 * 1024,
      uploadSize: uploadItem.file.size,
      storeFingerprintForResuming: true, // Store fingerprint for resuming
      removeFingerprintOnSuccess: true,  // Clear fingerprint after success

      // ====================================================================
      // Control which errors should be retried
      // ====================================================================
      onShouldRetry: (err: tus.DetailedError) => {
        const statusCode = err.originalResponse?.getStatus?.() || 0

        // DO NOT RETRY on 409 (Conflict) or 422 (Unprocessable Entity)
        if (statusCode === 409 || statusCode === 422) {
          return false
        }

        // DO NOT RETRY on any 4XX error (client error)
        if (statusCode >= 400 && statusCode < 500) {
          return false
        }

        // DO RETRY on 5XX errors (server error) or network errors (0)
        if (statusCode === 0 || (statusCode >= 500 && statusCode < 600)) {
          return true
        }

        // DO NOT RETRY on anything else
        return false
      },

      onError: (error: Error | tus.DetailedError) => {
        const detailed = error as tus.DetailedError
        const statusCode = detailed.originalResponse?.getStatus?.() || 0

        // Try to extract a clean message from the server's JSON response body
        let errorMessage: string | null = null
        try {
          const body = detailed.originalResponse?.getBody?.()
          if (body) {
            const json = JSON.parse(body)
            if (json.message) errorMessage = json.message
          }
        } catch { /* ignore malformed body */ }

        // Fall back to status-code-specific messages
        if (!errorMessage) {
          if (statusCode === 413)      errorMessage = 'File is too large to upload'
          else if (statusCode === 409 || statusCode === 422) errorMessage = 'File already exists and cannot be resumed'
          else if (statusCode === 403) errorMessage = 'You don\'t have permission to upload files'
          else if (statusCode === 0)   errorMessage = 'Network error — check your connection'
          else if (statusCode >= 500)  errorMessage = 'Server error — please try again'
          else                         errorMessage = 'Upload failed'
        }

        const index = uploads.value.findIndex(u => u.id === uploadItem.id)
        if (index !== -1) {
          uploads.value[index] = {
            ...uploads.value[index],
            status: 'error',
            error: errorMessage,
          }
        }

        // Signal completion (with null) so callers tracking batch
        // progress can detect when all uploads have settled.
        if (uploadItem.onComplete) {
          uploadItem.onComplete(null)
        }
      },

      onProgress: (bytesUploaded: number, bytesTotal: number) => {
        const percentage = (bytesUploaded / bytesTotal) * 100
        updateUploadProgress(uploadItem.id, percentage)
      },

      onChunkComplete: (_chunkSize: number, bytesAccepted: number, bytesTotal: number) => {
        const percentage = (bytesAccepted / bytesTotal) * 100
        updateUploadProgress(uploadItem.id, percentage)
      },

      onSuccess: async () => {
        const index = uploads.value.findIndex(u => u.id === uploadItem.id)
        if (index !== -1) {
          uploads.value[index] = {
            ...uploads.value[index],
            status: 'completed',
            progress: 100,
          }
        }

        // Extract the TUS filename from the upload URL for the callbacks
        const tusFilename = upload.url ? upload.url.split('/').pop() ?? null : null

        options.onUploaded?.(tusFilename, uploadItem)

        if (uploadItem.onComplete) {
          const resolved = options.resolveUploadedFile
            ? await options.resolveUploadedFile(tusFilename)
            : { filename: tusFilename }
          uploadItem.onComplete(resolved)
        }

        options.onSettled?.()
      },
    })

    uploadItem.tusUpload = upload

    // Check for previous uploads to resume
    upload.findPreviousUploads().then((previousUploads) => {
      if (previousUploads.length > 0) {
        upload.resumeFromPreviousUpload(previousUploads[0])
      }
      upload.start()
    }).catch((error) => {
      console.error('Error finding previous uploads:', error)
      upload.start()
    })
  }

  // File selection handler
  const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (!target.files || target.files.length === 0) {
      return
    }

    const files = [...target.files]
    addFilesToUpload(files)

    target.value = ''
  }

  // Returns true when the File entry is a real, allowed file (not a folder)
  const isAcceptedFile = (file: File) => {
    // Folders have no MIME type when dropped
    if (!file.type) return false
    if (blockedMimeTypes.has(file.type)) return false
    return true
  }

  // Add files to upload queue
  // options.onComplete(uploadedFile) is called after each file is saved
  // options.onRejected(rejectedFiles) is called when one or more files are rejected
  const addFilesToUpload = (files: File[], opts: AddFilesOptions = {}) => {
    const rejected: File[] = []

    files.forEach((file) => {
      if (!isAcceptedFile(file)) {
        rejected.push(file)
        return
      }

      const uploadId = ++uploadCounter
      const uploadItem: UploadItem = {
        id: uploadId,
        file: file,
        progress: 0,
        status: 'pending',
        tusUpload: null,
        error: null,
        onComplete: opts.onComplete ?? null,
        createdAt: Date.now(),
      }

      uploads.value.push(uploadItem)

      // The TUS client is fetched on demand, so starting is async: a client
      // that fails to load (offline, or the optional peer not installed)
      // marks the item rather than escaping as an unhandled rejection.
      startUpload(uploadItem).catch((error) => {
        console.error('Could not start upload:', error)
        const index = uploads.value.findIndex(u => u.id === uploadItem.id)
        if (index !== -1) {
          uploads.value[index] = {
            ...uploads.value[index],
            status: 'error',
            error: 'Upload could not be started',
          }
        }
        uploadItem.onComplete?.(null)
      })
    })

    if (rejected.length > 0 && opts.onRejected) {
      opts.onRejected(rejected)
    }

    return {
      accepted: files.length - rejected.length,
      rejected: rejected.length,
    }
  }

  // Pause upload
  const pauseUpload = (uploadItem: UploadItem) => {
    if (uploadItem.tusUpload) {
      uploadItem.tusUpload.abort(true)
      const index = uploads.value.findIndex(u => u.id === uploadItem.id)
      if (index !== -1) {
        uploads.value[index] = {
          ...uploads.value[index],
          status: 'paused',
        }
      }
    }
  }

  // Resume upload
  const resumeUpload = (uploadItem: UploadItem) => {
    const tusUpload = uploadItem.tusUpload
    if (!tusUpload) {
      console.error('No upload instance found')
      return
    }

    const index = uploads.value.findIndex(u => u.id === uploadItem.id)
    if (index !== -1) {
      uploads.value[index] = {
        ...uploads.value[index],
        status: 'uploading',
        error: null,
      }
    }

    // Use the URL storage API to resume
    tusUpload.findPreviousUploads().then((previousUploads) => {
      if (previousUploads.length > 0) {
        tusUpload.resumeFromPreviousUpload(previousUploads[0])
      }
      tusUpload.start()
    }).catch((error) => {
      console.error('Error finding previous uploads:', error)
      tusUpload.start()
    })
  }

  // Cancel upload
  const cancelUpload = (uploadItem: UploadItem) => {
    if (uploadItem.tusUpload) {
      uploadItem.tusUpload.abort()
    }
    if (uploadItem._clearTimer) clearTimeout(uploadItem._clearTimer)
    uploads.value = uploads.value.filter((u) => u.id !== uploadItem.id)
  }

  // Cancel all uploads
  const cancelAllUploads = () => {
    uploads.value.forEach((uploadItem) => {
      if (uploadItem.tusUpload) {
        uploadItem.tusUpload.abort()
      }
      if (uploadItem._clearTimer) clearTimeout(uploadItem._clearTimer)
    })
    uploads.value = []
  }

  // Format bytes
  const formatBytes = (bytes: number, decimals = 2) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const dm = decimals < 0 ? 0 : decimals
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
  }

  // Get status text
  const getStatusText = (upload: UploadItem) => {
    const statusTexts: Record<UploadStatus, string> = {
      pending: 'Pending...',
      uploading: 'Uploading...',
      paused: 'Paused',
      completed: 'Completed',
      error: 'Error',
    }
    return statusTexts[upload.status] || 'Unknown'
  }

  // Auto-remove completed uploads from the queue after 3 s so the
  // UploadQueue panel closes automatically when all files are done.
  watch(uploads, (val) => {
    val.forEach((item) => {
      if (item.status === 'completed' && !item._clearTimer) {
        item._clearTimer = setTimeout(() => {
          uploads.value = uploads.value.filter((u) => u.id !== item.id)
        }, 3000)
      }
    })
  }, { deep: true })

  // Abort in-flight uploads and clear all timers when the owning scope is torn
  // down, so navigating away doesn't leave orphaned uploads or fire timers into
  // a dead component.
  onScopeDispose(() => {
    uploads.value.forEach((item) => {
      if (item._clearTimer) clearTimeout(item._clearTimer)
      item.tusUpload?.abort()
    })
  })

  return {
    uploads,
    handleFileSelect,
    addFilesToUpload,
    pauseUpload,
    resumeUpload,
    cancelUpload,
    cancelAllUploads,
    formatBytes,
    getStatusText,
  }
}
