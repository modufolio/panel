# Session timeout warning

`SessionTimeoutDialog` warns before the server's idle timeout signs the user
out, and offers to stay signed in. `useSessionTimeout` is the countdown behind
it, usable on its own.

```vue
<SessionTimeoutDialog
  v-if="session"
  :timeout="session.remaining"
  :warn-before="session.warnBefore"
/>
```

The server decides everything; the dialog only renders it. AppKit's firewall
option `idle_timeout` is the deadline, and the application sends the time left
as a shared Inertia prop. Nothing here works without that half.

## Why the countdown is a duration, not a deadline

The server sends **seconds remaining**, never an absolute timestamp. A
browser's clock can be minutes off the server's, and an absolute deadline would
inherit that error — showing time the session does not have, or expiring a
session that is still alive. The client adds the duration to its own
`Date.now()`, so only the *elapsed* time has to agree, and that is the one
thing both clocks measure identically.

## Why there is no heartbeat

The composable never asks the server anything. Every completed Inertia
navigation is treated as activity, carrying a fresh `session.remaining` with
it — the same requests that slide the deadline server-side, so the two clocks
stay in step for free.

This is deliberate. A polling "am I still alive?" request is still a request,
and a request renews the session — a heartbeat keeps a session alive forever
and the timeout never fires. If an application does add a status endpoint, its
path must be declared in the firewall's `idle_ignore_paths`, or it defeats the
feature it exists to support.

## Why the remaining time is recomputed, never decremented

`remaining` is always `deadline - Date.now()`. Timers do not fire while a
laptop sleeps, so a counter that decremented once per tick wakes up minutes
behind the server and cheerfully reports time the session has already lost. The
interval only decides *when to look*; the wall clock decides the answer.

The same applies to a **hidden tab**, where browsers throttle `setInterval` to
roughly once a minute — the tick that would open the warning may not run at all
while the user is away. That is why becoming visible re-checks immediately
instead of waiting out the next tick: a tab returned to after a break either
shows the warning with the correct time left, or redirects because the deadline
has already passed. Expect the dialog to appear *on focus* rather than in the
background; a warning nobody can see has nothing to offer anyway.

## Across tabs

Tabs share the deadline through one `localStorage` timestamp, and each adopts
any deadline later than its own. Activity in one tab therefore dismisses the
warning in the others — which is correct, because the deadline is a property of
the session, not of the tab.

A stored timestamp is used rather than `BroadcastChannel` messages on purpose:
a suspended or frozen tab misses messages sent while it slept, but reads a
timestamp correctly the moment it wakes. Loading a page publishes its deadline
too, so a plain navigation in any tab is enough to resynchronise the rest.

`localStorage` access is wrapped: in a private window where it throws, the
dialog degrades to correct single-tab behaviour rather than breaking.

## Accessibility

A session timeout is a time limit **set by the content**, so WCAG 2.2 SC 2.2.1
(Level A) applies even though the limit exists for security reasons. The
*Extend* branch is the one to satisfy: warn before expiry, give the user at
least **20 seconds** to respond with a simple action, and allow extending at
least **ten times**. The defaults warn two minutes out and never cap
extensions, which clears both comfortably. If you shorten `warnBefore`, keep it
well above 20 seconds, and never add an extension limit.

Two details in the markup are worth preserving if you restyle it:

- The visible countdown changes every second, so it is `aria-hidden`. A
  separate visually-hidden `aria-live="assertive"` region carries the same
  message, **rounded up to 20-second steps** — a screen reader announces a
  handful of times instead of 120.
- Dismissing the dialog — Escape, or the overlay — extends the session rather
  than closing it silently. Closing a warning should not be a way to lose your
  work, and the dialog is not a thing the user chose to open.

## Expiry

When the countdown reaches zero the browser does a **full page load** to the
login page, not an Inertia visit: the session is gone, so the panel shell has
to be replaced rather than left on screen wrapped around a dead SPA. Late
in-flight responses cannot resurrect it — activity is ignored once expired.
