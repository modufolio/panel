import type { Component } from 'vue'

/**
 * Application-registered icons. The panel's <Icon> ships a heroicons-based
 * default set; apps add or override names at boot:
 *
 *   registerIcons({ camera: CameraIcon, 'my-brand': BrandMark })
 *
 * Registered names take precedence over built-ins with the same name.
 */
const customIcons: Record<string, Component> = {}

export function registerIcons(icons: Record<string, Component>): void {
  Object.assign(customIcons, icons)
}

export function getCustomIcon(name: string): Component | undefined {
  return customIcons[name]
}
