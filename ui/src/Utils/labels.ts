/**
 * A readable label derived from a key, for a page that built its own props.
 *
 * Everything the server sends arrives labelled — the resource's `title` and
 * `label`, every column, every form field, every drawer-grid key, every tab
 * and fieldset a field refers to. The generated pages therefore never call
 * these. They exist for a hand-written page that hands a component its own
 * `include` map or `layout` without labels, and for a consuming app that
 * wants the same wording for something the panel does not name.
 *
 * Two registers, and the split is deliberate: a drawer grid or a tab reads
 * in title case — "Form Submissions" — while a fieldset legend reads in
 * sentence case, "Postal code". Each caller asks for the one its surface
 * uses. The server's `Modufolio\Panel\Support\Label` makes the same split;
 * a page that mixes its own labels with the server's should pick the
 * register the surface already uses.
 */

/** Separator runs collapsed to single spaces, so `first__name` and `first-name` agree. */
function words(key: string): string {
  return key.replace(/[_-]+/g, ' ').trim()
}

/** Title case: `'form_submissions'` → `'Form Submissions'`. */
export function titleLabel(key: string): string {
  return words(key).replace(/\b\w/g, (character) => character.toUpperCase())
}

/** Sentence case: `'postal_code'` → `'Postal code'`. */
export function sentenceLabel(key: string): string {
  const text = words(key)

  return text.charAt(0).toUpperCase() + text.slice(1)
}
