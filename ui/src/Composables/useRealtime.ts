import { onScopeDispose } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Centrifuge } from 'centrifuge'
import { panelUrl } from '../Utils/url'

/**
 * Live updates for a panel page.
 *
 * The server sends a *nudge* — "job-postings changed", and nothing else — and
 * the page answers with a partial reload through the route it would have used
 * anyway. Two reasons it is shaped that way rather than pushing rows:
 *
 * 1. Authorization stays in one place. The reload goes through the normal
 *    authorized route, so the server's own rules decide what comes back; a
 *    pushed row would have to restate those rules as channel permissions and
 *    could drift from them.
 * 2. Nothing secret rides the channel, so a channel is cheap to reason about.
 *
 * Transport is Centrifugo over WebSocket. Nothing here knows which PHP runtime
 * produced the page: the token endpoint and the publish are both plain HTTP, so
 * a worker and an FPM pool behave identically.
 */

export interface RealtimeConfig {
    url: string
    token: string
    channel_prefix: string
}

/** The header a write carries so its own tab can ignore the echo. */
export const REALTIME_CLIENT_HEADER = 'X-Panel-Client'

/** The header a nudge reload carries so the server leaves the flash bag alone. */
export const REALTIME_NUDGE_HEADER = 'X-Panel-Nudge'

let client: Centrifuge | null = null
let clientToken: string | null = null
let clientId: string | null = null
let visitHookInstalled = false

/**
 * This tab's connection id, once connected — null before that and after a
 * disconnect. A write sends it so the nudge it causes can be ignored here:
 * the acting tab already has the server's answer.
 */
export function realtimeClientId(): string | null {
    return clientId
}

/**
 * Every Inertia visit carries the connection id, so any write — a form post, a
 * row action, a drawer save — is attributable to the tab that made it without
 * each call site remembering to say so. `apiFetch` adds the same header.
 */
function installVisitHook(): void {
    if (visitHookInstalled) {
        return
    }

    visitHookInstalled = true

    router.on('before', (event) => {
        if (clientId !== null) {
            event.detail.visit.headers[REALTIME_CLIENT_HEADER] = clientId
        }
    })
}

function connection(config: RealtimeConfig): Centrifuge {
    // A page load brings a fresh token; reuse the socket unless it changed.
    if (client !== null && clientToken === config.token) {
        return client
    }

    client?.disconnect()

    client = new Centrifuge(config.url, {
        token: config.token,
        // The token expires (an hour, by default). Without this the socket
        // would drop silently mid-afternoon and the panel would look live
        // while being anything but.
        getToken: async () => {
            const response = await fetch(panelUrl('/realtime/token'), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })

            if (!response.ok) {
                // Signed out, or the server stopped offering realtime. Throwing
                // stops Centrifugo's retry loop rather than hammering a 401.
                throw new Error(`realtime token refused (${response.status})`)
            }

            return ((await response.json()) as { token: string }).token
        },
    })

    client.on('connected', (ctx) => {
        clientId = ctx.client
    })

    client.on('disconnected', () => {
        clientId = null
    })

    clientToken = config.token
    client.connect()
    installVisitHook()

    return client
}

export interface LiveUpdateOptions {
    /** Props to re-fetch when the nudge arrives — Inertia's partial reload. */
    only?: string[]
    /** Anything else to do with the nudge; the reload happens regardless. */
    onChange?: (payload: Record<string, unknown>) => void
}

/**
 * Subscribe this page to a resource's nudges for as long as it is mounted.
 *
 * @param resource The resource key the server publishes under, e.g.
 *                 `Publisher::changed('job-postings')`.
 */
export function useLiveUpdates(resource: string, options: LiveUpdateOptions = {}): void {
    const page = usePage()
    const config = page.props.realtime as RealtimeConfig | null | undefined

    if (!config) {
        return
    }

    const client = connection(config)
    const channel = config.channel_prefix + resource

    // A Subscription stays registered on the client by channel name after
    // unsubscribe() — only removeSubscription() forgets it. Remounting this
    // page for the same channel (leave and come back) would otherwise call
    // newSubscription() on a channel that is still registered and throw
    // "Subscription to the channel ... already exists", which crashed the
    // page before this reused the leftover registration instead.
    const subscription = client.getSubscription(channel) ?? client.newSubscription(channel)

    subscription.on('publication', (ctx) => {
        const payload = (ctx.data ?? {}) as Record<string, unknown>

        // Our own write. The response to it already updated this tab, and
        // reloading again would throw away a scroll position or a half-typed
        // filter for nothing.
        if (typeof payload.origin === 'string' && payload.origin === clientId) {
            return
        }

        options.onChange?.(payload)

        // reload() forces preserveScroll and preserveState — its options type
        // omits them — which is what a nudge wants: the update arrives while
        // someone is reading, and jumping them to the top of the table would
        // be worse than a stale row.
        router.reload({
            only: options.only,
            // Somebody else's action caused this, so the server must not hand
            // this tab the flash message meant for the tab that acted.
            headers: { [REALTIME_NUDGE_HEADER]: '1' },
        })
    })

    subscription.subscribe()

    onScopeDispose(() => {
        subscription.unsubscribe()
        subscription.removeAllListeners()
        client.removeSubscription(subscription)
    })
}
