import type { App, Component } from 'vue'
import { setPanelBaseUrl } from './Utils/url'
import { setTeleportTarget } from './Primitives/teleportTarget'
import { registerIcons } from './Components/Core/iconRegistry'
import { registerFieldType } from './Components/Fields/useBlueprint'
import { setMediaEndpoints, type MediaEndpoints } from './Components/Media/mediaEndpoints'

export interface CreatePanelOptions {
  /** Mount path of the panel backend, e.g. '/panel' or '/admin'. */
  baseUrl?: string
  /** Extra icons for <Icon>, name → component. Override built-ins by name. */
  icons?: Record<string, Component>
  /** App-specific blueprint field types, type → async component loader. */
  fields?: Record<string, () => Promise<Component | { default: Component }>>
  /**
   * Where overlays (dialogs, drawers, dropdowns) are teleported.
   * Defaults to 'body'; set it when overlays must live inside a themed or
   * transformed subtree instead.
   */
  teleportTarget?: string | HTMLElement
  /**
   * Backend paths the media picker calls, relative to `baseUrl`. Defaults to
   * `/api/media/picker` and `/api/media/picker/albums`; override them when the
   * app serves media under different routes.
   */
  media?: Partial<MediaEndpoints>
}

/**
 * Vue plugin — the single required setup call for panel consumers:
 *
 *   app.use(createPanel({
 *     baseUrl: '/panel',
 *     icons: { camera: CameraIcon },
 *     fields: { 'block-editor': () => import('./Fields/BlockEditorField.vue') },
 *   }))
 */
export function createPanel(options: CreatePanelOptions = {}) {
  // Apply configuration immediately (not in install): registries and the
  // base URL are module-level, and eagerly-imported modules may build URLs
  // with panelUrl() before the Vue app is mounted.
  if (options.baseUrl !== undefined) setPanelBaseUrl(options.baseUrl)
  if (options.icons) registerIcons(options.icons)
  if (options.teleportTarget !== undefined) setTeleportTarget(options.teleportTarget)
  setMediaEndpoints(options.media)
  for (const [type, loader] of Object.entries(options.fields ?? {})) {
    registerFieldType(type, loader)
  }

  return {
    install(_app: App) {
      // Reserved for future app-level wiring (provide/inject, components).
    },
  }
}
