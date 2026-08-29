import {
  autoUpdate,
  flip,
  offset as offsetMiddleware,
  shift,
  size,
  useFloating,
  type Placement,
} from '@floating-ui/vue'
import type { CSSProperties, Ref } from 'vue'

export interface AnchoredPositionOptions {
  /** Preferred side; flipped automatically when it does not fit. */
  placement?: Placement
  /** Gap between anchor and panel, in pixels. */
  offset?: number
  /** Keep this much clearance from the viewport edge. */
  padding?: number
  /** Size the panel to the anchor — what a combobox listbox wants. */
  matchWidth?: boolean
  /** Cap the panel's height to the space actually available. */
  fitViewport?: boolean
  /** Never shrink the panel below this, even in a cramped viewport. */
  minHeight?: number
}

export interface AnchoredPosition {
  /** Bind to the panel's `style`. */
  floatingStyles: Readonly<Ref<CSSProperties>>
  /** The side finally used, after collision handling. */
  placement: Readonly<Ref<Placement>>
}

/**
 * Position a floating panel against its trigger.
 *
 * Replaces the hand-rolled `getBoundingClientRect()` + clamp that each dropdown
 * used to carry: flipping when the panel would run off the bottom, shifting it
 * back inside the viewport, capping its height, and — via `autoUpdate` —
 * following the anchor through scrolls, resizes and layout changes without
 * every component registering its own scroll and resize listeners.
 */
export function useAnchoredPosition(
  anchor: Ref<HTMLElement | null>,
  panel: Ref<HTMLElement | null>,
  open: Ref<boolean>,
  options: AnchoredPositionOptions = {},
): AnchoredPosition {
  const {
    placement = 'bottom-end',
    offset = 8,
    padding = 8,
    matchWidth = false,
    fitViewport = true,
    minHeight = 160,
  } = options

  const { floatingStyles, placement: resolvedPlacement } = useFloating(anchor, panel, {
    open,
    placement,
    strategy: 'fixed',
    whileElementsMounted: autoUpdate,
    middleware: [
      offsetMiddleware(offset),
      flip({ padding }),
      shift({ padding }),
      size({
        padding,
        apply({ availableHeight, rects, elements }) {
          if (matchWidth) {
            elements.floating.style.width = `${rects.reference.width}px`
          }
          if (fitViewport) {
            // Also exposed as a custom property so a panel can size an inner
            // scroll area against it rather than its own outer box.
            const height = Math.max(minHeight, availableHeight)
            elements.floating.style.maxHeight = `${height}px`
            elements.floating.style.setProperty('--panel-available-height', `${height}px`)
          }
        },
      }),
    ],
  })

  return { floatingStyles, placement: resolvedPlacement }
}
