/**
 * Where overlays render. `body` works in any host page; an app that needs its
 * overlays inside a themed or transformed subtree overrides it once, through
 * createPanel({ teleportTarget }), rather than each component hard-coding a
 * selector the host has to know to provide.
 */
let target: string | HTMLElement = 'body'

export function setTeleportTarget(value: string | HTMLElement): void {
  target = value
}

export function getTeleportTarget(): string | HTMLElement {
  return target
}
