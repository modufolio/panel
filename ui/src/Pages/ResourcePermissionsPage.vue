<template>
  <div>
    <Head title="Permissions" />

    <PageHeader title="Permissions" />

    <!--
      The matrix is fed the report the server built through
      PermissionInspectorFactory — the application's own routes, roles and idea
      of a user. This page owns only the chrome.
    -->
    <PermissionsMatrix :report="report" />
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import { Head } from '@inertiajs/vue3'
import PageHeader from '../Components/Layout/PageHeader.vue'
import PermissionsMatrix from '../Components/Permissions/PermissionsMatrix.vue'
import type { PermissionReport } from '../Components/Permissions/PermissionsMatrix.vue'

defineProps({
  report: { type: Object as PropType<PermissionReport>, required: true },
})
</script>
