/**
 * URL and HTML-escaping helpers shared by the rich-text editors.
 *
 * The panel has several hand-rolled contenteditable editors that serialize
 * their content to HTML strings by hand. These helpers give them one place to
 * escape attribute values and to reject dangerous URL schemes so a typed or
 * pasted `javascript:` link can't turn into stored XSS.
 */

// Schemes we allow in an href without rewriting.
const SAFE_SCHEME = /^(https?:|mailto:|tel:)/i;

// Relative / same-document references (paths, anchors, `./foo`).
const RELATIVE_REF = /^(\/|#|\.)/;

// Any explicit `scheme:` prefix (used to detect schemes we don't allow).
const HAS_SCHEME = /^[a-z][a-z0-9+.-]*:/i;

// Characters that cannot appear in a scheme (RFC 3986: ALPHA *( ALPHA / DIGIT /
// "+" / "-" / "." ) ":"). Removing them collapses a value to the scheme the
// browser will actually see — see readScheme below.
const NOT_SCHEME_CHAR = /[^A-Za-z0-9+.\-:]/g;

/**
 * Escape a string for safe interpolation into a double-quoted HTML attribute
 * value. Escaping `"` and `<`/`>`/`&` prevents attribute-injection breakouts.
 */
export function escapeHtml(value: string): string {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Reduce a URL to the scheme a browser will act on.
 *
 * A browser ignores ASCII whitespace and control characters while resolving a
 * URL, so `java\nscript:alert(1)` runs exactly like `javascript:alert(1)`. Read
 * literally, though, that value has no scheme at all: `^[a-z][a-z0-9+.-]*:`
 * stops at the newline and never reaches the colon. A check that reads the
 * scheme from the raw string therefore concludes "no scheme here" and hands the
 * value to whichever branch treats scheme-less input as safe.
 *
 * Dropping every character that cannot legally appear in a scheme closes that
 * gap without needing to enumerate what the noise might be.
 *
 * Mirrored in PHP by `App\Content\Url::readScheme()` — change both together.
 */
function readScheme(url: string): string {
  return url.replace(NOT_SCHEME_CHAR, '');
}

/**
 * Return `url` if it is safe to use as an href, otherwise ''.
 *
 * Safe means one of: a scheme on the allowlist (http/https/mailto/tel), a
 * relative or same-document reference, or no scheme at all (a bare host like
 * `example.com`). Everything else — `javascript:`, `data:`, `vbscript:`, and
 * equally anything we simply do not recognise — is rejected.
 *
 * Deliberately an allowlist. A denylist has to name every scheme that can
 * execute and silently admits the one nobody thought of.
 */
export function sanitizeUrl(url: string): string {
  const trimmed = String(url).trim();
  if (!trimmed) return '';

  // Relative and same-document references carry no scheme to inspect. Checked
  // against the raw value, since readScheme() would strip the leading `/` or `#`.
  if (RELATIVE_REF.test(trimmed)) return trimmed;

  const scheme = readScheme(trimmed);

  if (SAFE_SCHEME.test(scheme)) return trimmed;
  // Declares a scheme, but not one we allow.
  if (HAS_SCHEME.test(scheme)) return '';
  // No scheme at all.
  return trimmed;
}

/**
 * Coerce user-typed link input into a safe href value:
 *   example.com   → https://example.com
 *   /path, #hash  → unchanged
 *   mailto:a@b.c  → unchanged
 *   javascript:…  → '' (rejected)
 */
export function normalizeUrl(input: string): string {
  const trimmed = String(input).trim();
  if (!trimmed) return '';
  if (sanitizeUrl(trimmed) === '') return '';
  if (RELATIVE_REF.test(trimmed) || SAFE_SCHEME.test(trimmed)) return trimmed;
  return `https://${trimmed}`;
}

// ── Panel mount path ─────────────────────────────────────────────────────────

// Where the panel is mounted, e.g. '/panel' or '/admin'. Configured once at
// boot (createPanel({ baseUrl }) once the plugin exists); package code builds
// every panel URL through panelUrl() and never hardcodes the mount path.
let panelBaseUrl = '';

export function setPanelBaseUrl(base: string): void {
  // Normalize: keep a leading slash, drop a trailing one ('' stays '').
  const trimmed = String(base).trim().replace(/\/+$/, '');
  panelBaseUrl = trimmed === '' || trimmed.startsWith('/') ? trimmed : `/${trimmed}`;
}

export function getPanelBaseUrl(): string {
  return panelBaseUrl;
}

/**
 * Prefix a panel-relative path with the configured mount path:
 *   panelUrl('/api/media')  →  '/panel/api/media'   (baseUrl '/panel')
 */
export function panelUrl(path: string): string {
  const suffix = path.startsWith('/') ? path : `/${path}`;
  return `${panelBaseUrl}${suffix}`;
}
