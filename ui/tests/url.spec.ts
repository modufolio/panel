import { describe, it, expect } from 'vitest'
import { escapeHtml, sanitizeUrl, normalizeUrl } from '../src/index'

// Browsers strip ASCII whitespace and control characters while resolving a URL,
// so these all execute in an href exactly as the plain payload would. Built with
// fromCharCode so the control characters survive editors and diffs intact.
const C = String.fromCharCode
const OBFUSCATED = {
  'LF in scheme':      'java' + C(10) + 'script:alert(1)',
  'CR in scheme':      'java' + C(13) + 'script:alert(1)',
  'TAB in scheme':     'java' + C(9) + 'script:alert(1)',
  'NUL in scheme':     'java' + C(0) + 'script:alert(1)',
  'uppercase + LF':    'JAVA' + C(10) + 'SCRIPT:alert(1)',
  'LF in data:':       'dat' + C(10) + 'a:text/html,x',
  'TAB in vbscript:':  'vb' + C(9) + 'script:msgbox(1)',
  'leading controls':  C(1) + C(2) + 'javascript:alert(1)',
}

describe('escapeHtml', () => {
  it('escapes the HTML-significant characters', () => {
    expect(escapeHtml('a"b<c>d&e\'f')).toBe('a&quot;b&lt;c&gt;d&amp;e&#39;f')
  })
  it('leaves plain text untouched', () => {
    expect(escapeHtml('https://example.com/path')).toBe('https://example.com/path')
  })
})

describe('sanitizeUrl', () => {
  it('keeps safe schemes', () => {
    for (const url of ['https://x.com', 'http://x.com', 'mailto:a@b.com', 'tel:+1']) {
      expect(sanitizeUrl(url)).toBe(url)
    }
  })
  it('keeps relative and anchor references', () => {
    for (const url of ['/about', '#top', './x', '../y']) {
      expect(sanitizeUrl(url)).toBe(url)
    }
  })
  it('keeps a bare host (no scheme)', () => {
    expect(sanitizeUrl('example.com')).toBe('example.com')
  })
  it('rejects dangerous schemes', () => {
    for (const url of [
      'javascript:alert(1)',
      'JavaScript:alert(1)',
      ' javascript:alert(1)',
      'data:text/html,<script>alert(1)</script>',
      'vbscript:msgbox(1)',
    ]) {
      expect(sanitizeUrl(url)).toBe('')
    }
  })
  it.each(Object.entries(OBFUSCATED))(
    'rejects a dangerous scheme hidden by a control character (%s)',
    (_label, url) => {
      expect(sanitizeUrl(url)).toBe('')
    },
  )
})

describe('sanitizeUrl is an allowlist, not a denylist', () => {
  it('rejects any scheme it does not recognise, dangerous or not', () => {
    // The point of an allowlist: it does not have to predict what is harmful.
    for (const url of [
      'javascript:alert(1)',
      'vbscript:msgbox(1)',
      'livescript:x',
      'mocha:x',
      'jar:http://x/!/y',
      'data:text/html,<script>alert(1)</script>',
      'ftp://example.com',
      'file:///etc/passwd',
      'ws://example.com',
      'made-up-scheme:whatever',
    ]) {
      expect(sanitizeUrl(url)).toBe('')
    }
  })
})

describe('normalizeUrl', () => {
  it('prefixes a bare host with https://', () => {
    expect(normalizeUrl('example.com')).toBe('https://example.com')
  })
  it('passes through already-schemed and relative values', () => {
    expect(normalizeUrl('https://x.com')).toBe('https://x.com')
    expect(normalizeUrl('mailto:a@b.com')).toBe('mailto:a@b.com')
    expect(normalizeUrl('/about')).toBe('/about')
  })
  it('rejects dangerous schemes', () => {
    expect(normalizeUrl('javascript:alert(1)')).toBe('')
    expect(normalizeUrl('data:text/html,x')).toBe('')
  })
  it.each(Object.entries(OBFUSCATED))(
    'never prefixes an obfuscated payload into a link (%s)',
    (_label, url) => {
      expect(normalizeUrl(url)).toBe('')
    },
  )
  it('returns empty for blank input', () => {
    expect(normalizeUrl('   ')).toBe('')
  })
})
