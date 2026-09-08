/**
 * One entry in a {@link DrawerFieldGrid}.
 *
 * Declared here rather than inside the SFC so consumers can import the type
 * (`<script setup>` cannot export type declarations).
 */
export interface DrawerField {
  /** Stable identifier — also the per-field slot name (`#field-{key}`). */
  key: string
  label: string
  /** Display text; already stringified, with blanks rendered as an em dash. */
  value: string
  /** Claim the full row rather than one column. */
  wide?: boolean
  /**
   * Span this many grid rows (2–4). Auto-placement fills the rows beside it
   * with the fields that follow, so a thumbnail declared with `rows: 3`
   * sits square next to three fields instead of shrinking into one cell.
   */
  rows?: number
  /**
   * Where this value points, when it is a reference the presenter gave an
   * `href`. Rendered as a DrawerLink so the target stacks over the current
   * frame instead of replacing it.
   */
  href?: string
  navigation?: 'drawer' | 'visit'
  /** The value before stringification, for slot consumers. */
  raw?: unknown
  /**
   * A thumbnail to render instead of `value` — recognised from a raw value
   * shaped `{ thumbnail_url, url }` (the convention presenters already use
   * for a media reference, e.g. a post's cover). Absent for every other
   * field, including a plain URL string, which stays text: only a presenter
   * that opted into the object shape gets the image treatment.
   */
  image?: string
  /**
   * A break rather than a field: a rule (`line`) or a gap (`space`) across
   * the full row, the same breaks the form declares between its fields.
   */
  separator?: 'line' | 'space'
  /**
   * Where this field's picker posts a chosen image — server-stamped, present
   * only when the viewer may write `pickTarget`. Absent, the field's empty
   * state is a plain, non-interactive placeholder.
   */
  pickUrl?: string | null
  /** The form field key the picker's selection is posted as. */
  pickTarget?: string | null
  /** The empty state's wording, when the resource declared one; otherwise "Choose image". */
  pickLabel?: string | null
}
