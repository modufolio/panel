import { computed, reactive, ref, type ComputedRef, type Ref } from 'vue'

export interface FieldConfig {
  name: string
  label: string
  type: 'text' | 'email' | 'select' | 'textarea' | 'number' | 'hidden'
    | 'date' | 'time' | 'datetime' | 'checkbox'
  placeholder?: string
  required?: boolean
  maxlength?: number
  /** Bounds for date/time/datetime inputs, in the input's own value format. */
  min?: string
  max?: string
  options?: Array<{ value: string | number; label: string }>
  grid?: 'full' | 'half'
}

export interface FormState {
  visible: boolean
  saving: boolean
  mode: 'create' | 'edit'
  /**
   * Identifier of the record the form hangs off, and of the row being edited.
   *
   * Both accept strings: a parent is addressed by uuid in this panel, and only
   * some children keep a numeric id. These were typed `number`, which every
   * caller had always violated — the contact uuid is a string — and nothing
   * caught it until the drawer's tab declaration made the call sites explicit.
   */
  parentId: string | number | null
  recordId: string | number | null
  [key: string]: unknown
}

export interface UseNestedDrawerFormReturn {
  state: FormState
  errors: Ref<Record<string, string>>
  serverError: Ref<string | null>
  isValid: () => boolean
  openForm: (parentId: string | number, recordId?: string | number, initialData?: Record<string, unknown>) => void
  closeForm: () => void
  resetForm: () => void
  getFormData: () => Record<string, unknown>
  /**
   * True while the open form holds edits that have not been submitted —
   * what an "unsaved changes" guard asks before letting a navigation
   * (including Logout) discard them. False whenever the form is closed:
   * a closed form's leftover state is not something the user can see or
   * lose. Also false while a submit is in flight, for the same reason.
   */
  isDirty: ComputedRef<boolean>
  setFieldValue: (field: string, value: unknown) => void
  submit: (onSubmit: (data: Record<string, unknown>, mode: 'create' | 'edit') => Promise<void>) => Promise<void>
}

/**
 * Composable for managing nested drawer form state and submission
 * Handles form visibility, validation, and data submission
 */
export function useNestedDrawerForm(
  fields: FieldConfig[],
  defaultValues?: Record<string, unknown>,
): UseNestedDrawerFormReturn {
  const state = reactive<FormState>({
    visible: false,
    saving: false,
    mode: 'create',
    parentId: null,
    recordId: null,
    ...defaultValues,
  })

  const errors = ref<Record<string, string>>({})
  const serverError = ref<string | null>(null)
  const initialValues = ref<Record<string, unknown>>({})

  function openForm(parentId: string | number, recordId?: string | number, initialData?: Record<string, unknown>) {
    state.parentId = parentId
    state.recordId = recordId ?? null
    state.mode = recordId ? 'edit' : 'create'
    state.visible = true
    serverError.value = null

    if (initialData) {
      initialValues.value = { ...initialData }
      fields.forEach((field) => {
        state[field.name] = initialData[field.name] ?? defaultValues?.[field.name] ?? ''
      })
    } else {
      resetForm()
    }
  }

  function closeForm() {
    state.visible = false
  }

  function resetForm() {
    errors.value = {}
    serverError.value = null
    fields.forEach((field) => {
      state[field.name] = defaultValues?.[field.name] ?? ''
    })
  }

  function getFormData(): Record<string, unknown> {
    const data: Record<string, unknown> = {}
    fields.forEach((field) => {
      data[field.name] = state[field.name]
    })
    return data
  }

  /**
   * Compared against what the form opened with — `initialValues` when
   * editing a row, the declared defaults when creating one — so typing a
   * value and typing it back out again is honestly not dirty.
   */
  const isDirty = computed(() => {
    if (!state.visible) return false

    // A submit in flight is not "unsaved". The values are already on their way
    // to the server, and the visit that reloads the record afterwards is part
    // of saving — prompting there asks the user whether to discard edits that
    // are being saved by the very navigation being questioned. A failed save
    // clears `saving` and leaves the form open, so the guard returns.
    if (state.saving) return false

    return fields.some((field) => {
      const current = state[field.name] ?? ''
      const original = state.mode === 'edit'
        ? initialValues.value[field.name] ?? defaultValues?.[field.name] ?? ''
        : defaultValues?.[field.name] ?? ''

      return String(current) !== String(original)
    })
  })

  function setFieldValue(fieldName: string, value: unknown) {
    state[fieldName] = value
    // Clear the per-field error as soon as the user provides a value
    if (errors.value[fieldName]) {
      delete errors.value[fieldName]
    }
  }

  function isValid(): boolean {
    errors.value = {}
    let isFormValid = true

    fields.forEach((field) => {
      if (field.required && !state[field.name]) {
        errors.value[field.name] = field.type === 'hidden'
          ? `${field.label} is required`
          : `${field.label} is required`
        isFormValid = false
      }

      if (field.type === 'email' && state[field.name]) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        if (!emailRegex.test(String(state[field.name]))) {
          errors.value[field.name] = 'Invalid email address'
          isFormValid = false
        }
      }
    })

    return isFormValid
  }

  async function submit(onSubmit: (data: Record<string, unknown>, mode: 'create' | 'edit') => Promise<void>) {
    if (!isValid()) return

    serverError.value = null
    state.saving = true
    try {
      const data = getFormData()
      await onSubmit(data, state.mode)
      closeForm()
    } catch (err: unknown) {
      serverError.value = err instanceof Error ? err.message : 'An unexpected error occurred'
    } finally {
      state.saving = false
    }
  }

  return {
    state,
    errors,
    serverError,
    isValid,
    openForm,
    closeForm,
    resetForm,
    getFormData,
    isDirty,
    setFieldValue,
    submit,
  }
}
