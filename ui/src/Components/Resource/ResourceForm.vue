<template>
  <BlueprintForm
    :model-value="formData"
    :fields="clientFields"
    :errors="form.errors"
    :label="cardLabel"
    @update:model-value="(val) => Object.assign(formData, val)"
  >
    <template #footer>
      <div class="flex items-center justify-between">
        <Action
          v-if="mode === 'edit' && resource.canDelete"
          :label="`Delete ${resource.label}`"
          color="danger"
          variant="text"
          @click="destroy"
        />
        <div class="flex items-center gap-3 ml-auto">
          <Action
            label="Cancel"
            color="gray"
            variant="outlined"
            @click="router.visit(resource.baseUrl)"
          />
          <Action
            :label="mode === 'create' ? `Create ${resource.label}` : `Update ${resource.label}`"
            color="primary"
            :disabled="form.processing"
            @click="submit"
          />
        </div>
      </div>
    </template>
  </BlueprintForm>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import type { PropType } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import type { FormDataConvertibleValue } from '@inertiajs/core'
import Action from '../Actions/Action.vue'
import BlueprintForm from '../Fields/BlueprintForm.vue'
import { fieldsFromSpec, initialValues } from '../Fields/fieldsFromSpec'
import { useUnsavedChangesWarning } from '../../Composables/useUnsavedChangesWarning'
import type { FieldDef } from '../Fields/useBlueprint'
import type { FieldSpec } from '../Fields/fieldsFromSpec'

/** Self-description from FormPresenter::props() — see its `resource` key. */
interface ResourceFormMeta {
  key: string
  baseUrl: string
  drawerType: string
  label: string
  canDelete?: boolean
}

/**
 * A PanelResource's create or edit form, from the props the generated
 * create/edit routes send: the resource's self-description and its fields.
 * The one form both generic pages share, and the one a hand-written page
 * reaches for when the fields are the resource's own.
 *
 * `fields` arrive exactly as PanelResource::formFields() declared them —
 * component, label, width, options, and rules as the PHP map. The rules are
 * converted with rulesFromSpec so the same declaration that the server
 * validates with also drives the client-side mirror.
 */
const props = defineProps({
  mode: { type: String as PropType<'create' | 'edit'>, required: true },
  resource: { type: Object as PropType<ResourceFormMeta>, required: true },
  fields: { type: Array as PropType<FieldSpec[]>, required: true },
  /** Present in edit mode: presentOne()'s view of the record. */
  record: { type: Object as PropType<Record<string, unknown> | null>, default: null },
})

const cardLabel = computed(() => `${props.resource.label} Details`)

// The rule-map conversion and the collection-safe defaults live in the panel
// package: the drawer's add form renders the same server declarations, and a
// second copy of either is a second chance for the two to disagree.
const clientFields = computed<FieldDef[]>(() => fieldsFromSpec(props.fields))

const initial = initialValues(props.fields, props.record)

/**
 * What a generated form's values are asserted to be. Inertia's FormDataType
 * generic cannot digest a dynamically-keyed record, and the full recursive
 * FormDataConvertible sends the checker into "excessively deep" — one level
 * of nesting is what initialValues() builds anyway: scalars, and arrays or
 * plain objects of scalars, never functions.
 */
type FormValue = FormDataConvertibleValue | FormDataConvertibleValue[] | Record<string, FormDataConvertibleValue>

const form = useForm(initial as Record<string, FormValue>)

// Reactive proxy so BlueprintForm's v-model syncs back to the Inertia form.
const formData = reactive({ ...form.data() })

watch(formData, (updated) => {
  Object.assign(form, updated)
})

/**
 * Leaving with unsaved edits asks first — including via Logout, which is an
 * ordinary Inertia visit and so passes through the same hook. Every
 * generated form gets this from here; before, only the four hand-written
 * pages had it, so most of the panel discarded edits silently.
 */
const { allowNextNavigation } = useUnsavedChangesWarning(form)

function submit() {
  // This navigation *is* the save; prompting about unsaved changes on the
  // way to saving them is the one case where the warning is nonsense.
  allowNextNavigation()

  if (props.mode === 'create') {
    form.post(props.resource.baseUrl)
  } else {
    form.put(`${props.resource.baseUrl}/${props.record?.id}`)
  }
}

function destroy() {
  if (confirm(`Delete this ${props.resource.label.toLowerCase()}? This cannot be undone.`)) {
    // The user has just confirmed destroying the record; asking again about
    // the edits they are destroying with it would be nagging.
    allowNextNavigation()
    router.delete(`${props.resource.baseUrl}/${props.record?.id}`)
  }
}
</script>
