<template>
  <div>
    <Head :title="`Create ${resource.label}`" />

    <PageHeader :title="`Create ${resource.label}`" />

    <ResourceForm mode="create" :resource="resource" :fields="fields" :layout="layout" />
  </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import type { PropType } from 'vue'
import PageHeader from '../Components/Layout/PageHeader.vue'
import ResourceForm from '../Components/Resource/ResourceForm.vue'
import type { ResourceFormMeta } from '../Components/Resource/resourceTypes'
import type { FieldSpec } from '../Components/Fields/fieldsFromSpec'
import type { FormLayout } from '../Components/Fields/useBlueprint'

defineProps({
  resource: { type: Object as PropType<ResourceFormMeta>, required: true },
  fields: { type: Array as PropType<FieldSpec[]>, required: true },
  layout: { type: Object as PropType<FormLayout>, default: () => ({}) },
})
</script>
