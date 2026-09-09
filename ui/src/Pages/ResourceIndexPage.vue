<template>
  <div>
    <Head :title="titleLabel(resource.key)" />

    <!--
      Everything a generated listing shows comes from ResourcePage, fed the
      server's props as they arrived — rows included, under the resource's own
      key. This page owns only the chrome around it. A resource that wants
      different markup overrides indexComponent() and calls
      useResourceListing() itself; see docs/graduating-a-resource.md.
    -->
    <ResourcePage v-bind="listingProps" />
  </div>
</template>

<script setup lang="ts">
import { computed, useAttrs, type PropType } from 'vue'
import { Head } from '@inertiajs/vue3'
import ResourcePage from '../Components/Resource/ResourcePage.vue'
import { type ResourceMeta } from '../Composables/useResourceListing'
import { titleLabel } from '../Utils/labels'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  resource: { type: Object as PropType<ResourceMeta>, required: true },
})

const attrs = useAttrs()

/**
 * Only `resource` is named above, so the rest of the listing's props — the
 * table schema, the rows under the resource's key, filters, stack, board —
 * arrive as attrs and pass through untouched. The cast is what says so: their
 * shapes are the server's to guarantee, and ResourcePage validates them.
 */
const listingProps = computed(
  () => ({ ...attrs, resource: props.resource }) as unknown as InstanceType<typeof ResourcePage>['$props'],
)
</script>
