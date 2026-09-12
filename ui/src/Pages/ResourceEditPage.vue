<template>
  <div>
    <Head :title="title" />

    <PageHeader :title="title">
      <template v-if="recordNavigation.previous || recordNavigation.next" #actions>
        <RecordNavigation
          :previous="recordNavigation.previous"
          :next="recordNavigation.next"
          :label="resource.label.toLowerCase()"
        />
      </template>
    </PageHeader>

    <ResourceForm mode="edit" :resource="resource" :fields="fields" :record="record" :layout="layout" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import type { PropType } from 'vue'
import PageHeader from '../Components/Layout/PageHeader.vue'
import RecordNavigation from '../Components/Layout/RecordNavigation.vue'
import ResourceForm from '../Components/Resource/ResourceForm.vue'
import type { ResourceFormMeta } from '../Components/Resource/resourceTypes'
import type { FieldSpec } from '../Components/Fields/fieldsFromSpec'
import type { FormLayout } from '../Components/Fields/useBlueprint'

type RecordNavigationUrls = { next: string | null, previous: string | null }

const props = defineProps({
  resource: { type: Object as PropType<ResourceFormMeta>, required: true },
  fields: { type: Array as PropType<FieldSpec[]>, required: true },
  record: { type: Object as PropType<Record<string, unknown>>, required: true },
  layout: { type: Object as PropType<FormLayout>, default: () => ({}) },
  // Absent for a resource whose controller does not supply it, which is why
  // this defaults to a pair of nulls rather than being required: the header
  // then renders no navigation at all.
  recordNavigation: {
    type: Object as PropType<RecordNavigationUrls>,
    default: () => ({ next: null, previous: null }),
  },
})

// A record's own title is what the user recognises the page by; the
// resource label is the fallback for one that carries neither.
const title = computed(
  () => (props.record.title as string) || (props.record.name as string) || `Edit ${props.resource.label}`,
)
</script>
