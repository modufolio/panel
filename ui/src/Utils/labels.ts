/**
 * A readable label derived from a key, when nothing declared one.
 *
 * Two registers, and the split is deliberate rather than an accident of four
 * separate copies: a resource title, a drawer grid or a tab reads in title
 * case — "Form Submissions" — while a form's fieldset legends read in
 * sentence case, "Postal code". Each caller asks for the one its surface
 * uses, and neither has to remember the pair of regexes that gets there.
 *
 * Mirrors `Modufolio\Panel\Support\Label` on the server, which derives the
 * same labels for the schema it sends: the two drifting apart is how the
 * same key comes to read differently depending on which side named it.
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
