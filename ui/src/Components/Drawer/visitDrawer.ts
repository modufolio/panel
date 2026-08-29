import { router } from '@inertiajs/vue3'
import { DRAWER_HEADER } from './useIsDrawer'

/**
 * One way to navigate the drawer stack.
 *
 * The drawer protocol is a convention held in three places at once — a header
 * string, an `only: ['stack']` partial reload, and the preserve flags that
 * keep the page underneath mounted. Every caller that re-typed that trio was
 * a chance to get one third of it wrong, and one did: a hand-written
 * `X-Drawer` header meant the server never took the drawer path, while
 * `only: ['stack']` still fetched enough for the drawer to *look* right.
 *
 * So this is the only place the trio is written. Anything that opens, drills
 * into, or closes a drawer goes through here.
 */
export interface VisitDrawerOptions {
  /**
   * Appended to the URL — the listing's filter/sort state, so a drawer's
   * record pagination stays inside the set the reader was looking at.
   */
  queryParams?: Record<string, unknown>
  /** Inertia partial-reload keys. Defaults to the stack alone. */
  only?: string[]
  preserveState?: boolean
  preserveScroll?: boolean
  replace?: boolean
}

/**
 * The visit options a drawer navigation needs, for the rare caller that must
 * hand them to Inertia itself (a form submission, say) rather than navigate.
 */
export function drawerVisitOptions(options: VisitDrawerOptions = {}) {
  return {
    only: options.only ?? ['stack'],
    preserveState: options.preserveState ?? true,
    preserveScroll: options.preserveScroll ?? true,
    ...(options.replace !== undefined ? { replace: options.replace } : {}),
    // The server keys "render the page underneath with a stack on top" off
    // exactly this header. Mirrors DrawerStack::HEADER in PHP.
    headers: { [DRAWER_HEADER]: '1' },
  }
}

/**
 * Append query parameters to a URL.
 *
 * Empty strings are kept: the server distinguishes "this filter is set to
 * nothing" from "this filter was not sent", and dropping them silently reset
 * filters when a drawer opened.
 */
export function withDrawerParams(url: string, params: Record<string, unknown> = {}): string {
  const query = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null) {
      query.append(key, String(value))
    }
  }

  const queryString = query.toString()

  if (queryString === '') {
    return url
  }

  return url + (url.includes('?') ? '&' : '?') + queryString
}

/** Navigate the drawer stack: open, drill into, go back, or close. */
export function visitDrawer(url: string, options: VisitDrawerOptions = {}): void {
  router.visit(withDrawerParams(url, options.queryParams), drawerVisitOptions(options))
}
