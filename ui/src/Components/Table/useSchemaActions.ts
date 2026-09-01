import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useDeleteConfirmation } from '../../Composables/useDeleteConfirmation'
import { visitDrawer } from '../Drawer/visitDrawer'
import {
  resolveRecordUrl,
  visibleRowActions,
  type SchemaRowAction,
  type SchemaBulkAction,
  type TableSchema,
  type RowActionHandler,
  type BulkActionHandler,
} from './tableSchema'
import { recordId, type TableRecord } from './tableTypes'

type Row = TableRecord

interface ActionOptions {
  schema: () => TableSchema
  /** Appended to record links so a drawer preserves the current list state. */
  queryParams: () => Record<string, unknown>
  /** The resource's own word for one record ('movie'), for the delete dialog. */
  recordLabel: () => string | undefined
  /** Row actions the schema declares but the table cannot service itself. */
  rowActionHandlers: () => Record<string, RowActionHandler>
  bulkActionHandlers: () => Record<string, BulkActionHandler>
}

/**
 * Runs the actions a schema declares, and owns the confirmations they need.
 *
 * The dialogs live here rather than on each page: an action that says
 * `confirm` should not require the page to remember to render one for it.
 */
export function useSchemaActions(options: ActionOptions) {
  /** The record the delete dialog is currently about, with its action. */
  const pendingDelete = ref<{ action: SchemaRowAction; record: Row } | null>(null)

  const pendingBulk = ref<{ action: SchemaBulkAction; records: Row[] } | null>(null)

  const deleteLabel = computed(() => options.recordLabel() || 'record')

  function rowActionsFor(record: Row): SchemaRowAction[] {
    return visibleRowActions(options.schema().actions, record)
  }

  function submitDelete(record: Row): void {
    const url = resolveRecordUrl(pendingDelete.value?.action.urlTemplate, record)

    if (url) router.delete(url, { preserveScroll: true })
  }

  /**
   * Two flows, because the composable decides at construction whether it has a
   * preview: an action carrying `previewUrl` asks the server what deleting
   * would cost, one without it degrades to a plain confirmation. Handing the
   * preview flow an empty URL instead would report every record as blocked.
   */
  const previewDeletion = useDeleteConfirmation<Row>({
    previewUrl: (record) => resolveRecordUrl(pendingDelete.value?.action.previewUrl, record) ?? '',
    onConfirm: submitDelete,
  })

  const plainDeletion = useDeleteConfirmation<Row>({
    // A soft delete is reversible, so the dialog must not claim otherwise.
    plan: () => ({ blocked: false, soft: pendingDelete.value?.action.soft ?? false }),
    onConfirm: submitDelete,
  })

  const deletion = computed(() =>
    pendingDelete.value?.action.previewUrl ? previewDeletion : plainDeletion,
  )

  function runRowAction(action: SchemaRowAction, record: Row): void {
    const url = resolveRecordUrl(action.urlTemplate ?? options.schema().recordUrl, record)

    switch (action.behaviour) {
      case 'dialog':
        // Identical navigation to a drawer — same stack, same protocol. Which
        // frame appears is the *server's* answer, carried on the stack item, so
        // the table never has to know what is on the other end of the URL.
      case 'drawer':
        // Same visit DrawerLink makes: the listing stays mounted underneath and
        // only the stack reloads.
        if (url) visitDrawer(url, { queryParams: options.queryParams() })
        break

      case 'visit':
        if (url) router.visit(url)
        break

      case 'delete':
        // Set first: which flow runs, and which URL it posts to, are both read
        // from this.
        pendingDelete.value = { action, record }
        void deletion.value.request(record)
        break

      default:
        // An action the page services: nothing happens without a handler, which
        // is better than throwing — a schema can outlive the page.
        options.rowActionHandlers()[action.name]?.(record, action)
    }
  }

  function performBulk(action: SchemaBulkAction, records: Row[]): void {
    if (action.behaviour === 'post' && action.url) {
      router.post(action.url, { ids: records.map(recordId) }, { preserveScroll: true })

      return
    }

    options.bulkActionHandlers()[action.name]?.(records, action)
  }

  function runBulkAction(action: SchemaBulkAction, records: Row[]): void {
    if (records.length === 0) return

    if (action.confirm) {
      pendingBulk.value = { action, records }

      return
    }

    performBulk(action, records)
  }

  const bulkMessage = computed(() => {
    const pending = pendingBulk.value

    if (!pending) return ''

    return (pending.action.confirmMessage ?? 'This will affect {count} selected record(s).').replace(
      '{count}',
      String(pending.records.length),
    )
  })

  function confirmBulk(): void {
    const pending = pendingBulk.value

    if (!pending) return

    pendingBulk.value = null
    performBulk(pending.action, pending.records)
  }

  return {
    rowActionsFor,
    runRowAction,
    deletion,
    deleteLabel,
    runBulkAction,
    pendingBulk,
    bulkMessage,
    confirmBulk,
  }
}
