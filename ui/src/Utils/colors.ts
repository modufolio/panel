/**
 * One colour vocabulary at the edge of the components.
 *
 * Two are in circulation: the panel's semantic tokens — `success`, `danger`,
 * `warning`, `info`, `primary`, `gray`, which is what the components' classes
 * are built from — and the plain hues an application's own enums tend to
 * carry (`green`, `yellow`, `blue`) through `HasColorInterface::getColor()`.
 *
 * A hue reaching a component that only knows tokens rendered grey, so every
 * page that showed such a value wrote its own `{ green: 'success', … }` map.
 * Translating here means an enum can say what it likes and be shown correctly
 * anyway; a value already a token passes through untouched, and anything
 * unrecognised falls back to `gray` as it always did.
 */
const SEMANTIC = ['primary', 'success', 'danger', 'warning', 'info', 'gray'] as const

export type SemanticColor = typeof SEMANTIC[number]

const HUES: Record<string, SemanticColor> = {
  green: 'success',
  emerald: 'success',
  teal: 'success',
  red: 'danger',
  rose: 'danger',
  pink: 'danger',
  yellow: 'warning',
  amber: 'warning',
  orange: 'warning',
  blue: 'info',
  sky: 'info',
  cyan: 'info',
  indigo: 'primary',
  violet: 'primary',
  purple: 'primary',
  grey: 'gray',
  slate: 'gray',
  zinc: 'gray',
  neutral: 'gray',
  stone: 'gray',
}

/** A semantic colour token, from a token or from a hue. `gray` for anything else. */
export function semanticColor(value: unknown): SemanticColor {
  if (typeof value !== 'string') {
    return 'gray'
  }

  return (SEMANTIC as readonly string[]).includes(value)
    ? value as SemanticColor
    : HUES[value.toLowerCase()] ?? 'gray'
}

/**
 * Whether `semanticColor` can place this value, as opposed to falling back.
 *
 * For `color` prop validators. Listing the six tokens inline is the obvious
 * thing and the wrong one: a component that renders through `semanticColor`
 * accepts the hues too, so a validator naming only the tokens warns in dev
 * about a value it then displays perfectly well.
 */
export function isSemanticColorInput(value: unknown): boolean {
  if (typeof value !== 'string') {
    return false
  }

  return (SEMANTIC as readonly string[]).includes(value) || value.toLowerCase() in HUES
}
