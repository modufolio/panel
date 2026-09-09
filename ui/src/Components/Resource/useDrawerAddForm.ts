import { ref, type Ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { apiFetch, ApiError } from '../../Utils/apiFetch'
import { fieldsFromSpec, initialValues, type FieldSpec } from '../Fields/fieldsFromSpec'
import { useDismissableLayer } from '../../Primitives/useDismissableLayer'
import type { FieldDef } from '../Fields/useBlueprint'
import type { StackItem } from '../Drawer/useDrawerStack'

/**
 * What the add form needs of the list it adds to. Structural on purpose: the
 * frame emits its own section type, the stack declares another, and both
 * carry these four.
 */
export interface AddableTab {
  label: string
  addLabel?: string | null
  addFields?: FieldSpec[]
  /** Where the row goes, stamped per record by the server. */
  addUrl?: string | null
}

export interface UseDrawerAddFormOptions {
  /** Where to send someone whose list has no inline form to render. */
  editUrl: (item: StackItem) => string
  /** The panel element, owned by the template that renders it. */
  panel: Ref<HTMLElement | null>
}

/**
 * Adding one row to a record's list, in a drawer over the drawer.
 *
 * Adding happens here rather than by navigating to the full form — reading a
 * record and extending one of its lists is one task, and leaving the record to
 * do it loses the place. The server names the endpoint, record and field
 * included: composing it from the page's own resource was wrong the moment a
 * frame of another resource was stacked over it, and it also had to know that
 * a tab may *read* from a display copy (`tag_list`) while the field that edits
 * the relation is the form's own (`tags`).
 */
export function useDrawerAddForm({ editUrl, panel }: UseDrawerAddFormOptions): {
  /** The open add-form, if any: which list is being added to, on which record. */
  addForm: Ref<{ tab: AddableTab; fields: FieldDef[] } | null>
  addValues: Ref<Record<string, unknown>>
  addErrors: Ref<Record<string, string>>
  addSaving: Ref<boolean>
  openAddForm: (item: StackItem, tab: AddableTab) => void
  closeAddForm: () => void
  submitAddForm: () => Promise<void>
} {
  const addForm = ref<{ tab: AddableTab; fields: FieldDef[] } | null>(null)
  const addValues = ref<Record<string, unknown>>({})
  const addErrors = ref<Record<string, string>>({})
  const addSaving = ref(false)

  function openAddForm(item: StackItem, tab: AddableTab): void {
    // Nothing to render a form from — fall back to the place that can edit it.
    if (!tab.addFields?.length) {
      router.visit(editUrl(item))
      return
    }

    // The declaration arrives with `rules` as the server's map; the blueprint
    // layer needs rule *functions*, and handing the raw map through throws the
    // moment a field validates. One conversion, shared with ResourceForm.
    const fields = fieldsFromSpec(tab.addFields)

    addValues.value = initialValues(fields)
    addErrors.value = {}
    addForm.value = { tab, fields }
  }

  function closeAddForm(): void {
    addForm.value = null
    addErrors.value = {}
    addSaving.value = false
  }

  /**
   * Register the panel in the overlay layer stack. Without it the drawer
   * beneath stays the topmost layer, so Escape closed the drawer out from
   * under an open form — and the drawer's focus trap kept reaching into it.
   */
  useDismissableLayer(() => addForm.value !== null, {
    elements: () => [panel.value],
    onDismiss: (reason) => { if (reason === 'escape') closeAddForm() },
    // The panel's own scrim already handles a press outside.
    dismissOnOutsidePointer: false,
    modalElement: () => panel.value,
  })

  async function submitAddForm(): Promise<void> {
    const open = addForm.value
    if (open === null || addSaving.value) {
      return
    }

    addSaving.value = true
    addErrors.value = {}

    const url = open.tab.addUrl ?? ''

    if (url === '') {
      addErrors.value = { _: 'This list cannot be added to here.' }
      addSaving.value = false
      return
    }

    try {
      await apiFetch(url, { method: 'POST', body: addValues.value })

      closeAddForm()
      // The server owns the record's shape, so re-read the frame rather than
      // patching a second copy of it here. Reloading the current URL rebuilds
      // whatever stack it addresses — the record alone, or the record with
      // another resource's frame over it — which navigating to this resource's
      // record URL would have collapsed to one frame.
      router.reload()
    } catch (error) {
      console.error(error)

      // A rejected submission names the fields it rejected; anything else is
      // one sentence under the form, the server's own where it gave one.
      if (error instanceof ApiError && error.status === 422) {
        const body = error.body as { errors?: Record<string, string> } | null
        addErrors.value = body?.errors ?? {}
      } else {
        addErrors.value = { _: error instanceof ApiError ? error.message : 'Could not save.' }
      }
    } finally {
      addSaving.value = false
    }
  }

  return {
    addForm,
    addValues,
    addErrors,
    addSaving,
    openAddForm,
    closeAddForm,
    submitAddForm,
  }
}
