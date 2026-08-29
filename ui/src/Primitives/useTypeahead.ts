/** How long typed characters keep accumulating into one search term. */
const RESET_AFTER_MS = 1000

/** A printable single character, i.e. something a user typed to search by. */
export function isTypeaheadKey(event: KeyboardEvent): boolean {
  return event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey
}

/** `values` rotated so it starts at `startIndex`. */
function wrapFrom<T>(values: T[], startIndex: number): T[] {
  return values.map((_, index) => values[(startIndex + index) % values.length])
}

/**
 * Jump to an item by typing its first letters — the keyboard behaviour every
 * native menu and select has, and the reason a long list is usable without a
 * mouse. Repeating one character cycles through the items starting with it.
 */
export function useTypeahead() {
  let search = ''
  let timer: ReturnType<typeof setTimeout> | undefined

  function reset(): void {
    search = ''
    if (timer !== undefined) clearTimeout(timer)
    timer = undefined
  }

  /**
   * Feed one keypress in and get the index of the item to move to, or -1 when
   * nothing matches.
   *
   * @param currentIndex Where focus is now; the search starts from there so
   *   typing the same letters twice advances rather than sticking.
   */
  function onTypeaheadKey(key: string, values: string[], currentIndex: number): number {
    if (values.length === 0) return -1

    search += key

    if (timer !== undefined) clearTimeout(timer)
    timer = setTimeout(reset, RESET_AFTER_MS)

    // "aaa" means "cycle through the a's", not "find something spelled aaa".
    const repeated = search.length > 1 && [...search].every((character) => character === search[0])
    const term = (repeated ? search[0] : search).toLowerCase()

    const start = Math.max(currentIndex, 0)
    const rotated = wrapFrom(values.map((value, index) => ({ value, index })), start)

    // A single character always moves on rather than re-selecting where we are.
    const candidates = term.length === 1
      ? rotated.filter((entry) => entry.index !== currentIndex)
      : rotated

    return candidates.find((entry) => entry.value.trim().toLowerCase().startsWith(term))?.index ?? -1
  }

  return { onTypeaheadKey, resetTypeahead: reset }
}
