/**
 * The generic resource pages, as an Inertia page-resolver map.
 *
 * Spread into the app's own resolver:
 *
 *   const pageModules = { ...resourcePages, ...appPages }
 *
 * Any PanelResource that does not override indexComponent() lists through
 * `Resource/Index`, and any resource declaring formFields() creates and edits
 * through `Resource/Create` and `Resource/Edit` — so a full-CRUD resource
 * needs no Vue file in the app at all. The layout stays the app's business:
 * these components declare none, so whatever the app's resolver assigns
 * applies to them like any other page.
 */
import type { Component } from 'vue'

export const resourcePages: Record<string, () => Promise<{ default: Component }>> = {
  'Resource/Index': () => import('./ResourceIndexPage.vue'),
  'Resource/Create': () => import('./ResourceCreatePage.vue'),
  'Resource/Edit': () => import('./ResourceEditPage.vue'),
  // The panel's own permission report at /panel/_permissions, generated
  // beside the resource routes.
  'Resource/Permissions': () => import('./ResourcePermissionsPage.vue'),
}
