// Tracks the current CSRF token value, shared to every Inertia page via
// DefaultProps (see src/Inertia/DefaultProps.php). The token is rotated by
// the server on login and on some state-changing requests, so this is kept
// in sync via Inertia's 'navigate' event rather than read once at boot.
let token: string | null = null

export function setCsrfToken(value: string | null | undefined): void {
  if (value) token = value
}

export function getCsrfToken(): string | null {
  return token
}
