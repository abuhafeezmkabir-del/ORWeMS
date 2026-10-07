# ORWeMS Phase 3D — Transport, Headers, Credentials & the Audit Trail

> **Architectural rule:** Phase 3D answers a different question from 3B
> (**WHAT** an operator may do) and 3C (**WHERE** that authority applies).
> It answers **WHETHER THE SYSTEM CAN BE TRUSTED WHILE THEY DO IT** — the
> transport cannot be read, the browser cannot be turned against the operator,
> the credential cannot outlive its usefulness, and every security-relevant
> fact is written down where nobody can rewrite it. Nothing in this phase
> grants a capability or widens a scope; it can only refuse, record, and
> redirect.

## 1. What this phase is

Security hardening of the authentication and authorization foundation:

1. **Transport & browser posture** — optional HTTPS redirect, optional HSTS,
   unconditional security headers (CSP included) on every response.
2. **Credential lifecycle** — password strength, an authenticated change flow,
   and an optional expiry policy that is off until WICHOV adopts it.
3. **The audit trail** — an append-only `audit_logs` table with a single
   writer, a stable event vocabulary, and automatic request forensics.

Deliberately **not** in this phase: a second factor. Two-factor
authentication is a credential-handling commitment (secret storage, recovery
codes, enrolment ceremonies) that must not be half-built. The
`ORWEMS_TWO_FACTOR_ENABLED` flag exists so the switch is visible and
testable, and a test asserts that nothing honours it yet.

## 2. Configuration, not hard-coded policy

Every production-only behaviour in this phase is configuration. The
repository never carries production values, and plain-HTTP local development
never breaks. Defaults are the local-development defaults; production
settings live in the deployment's own `.env` (never committed):

| Key | Default | Meaning |
| --- | --- | --- |
| `ORWEMS_FORCE_HTTPS` | `false` | Redirect plain HTTP to HTTPS |
| `ORWEMS_HSTS_ENABLED` | `false` | Send `Strict-Transport-Security` |
| `ORWEMS_HSTS_MAX_AGE` | `31536000` | HSTS `max-age`, seconds |
| `ORWEMS_HSTS_PRELOAD` | `false` | Append `preload` to HSTS |
| `ORWEMS_CSP_ENABLED` | `true` | Send a Content-Security-Policy |
| `ORWEMS_CSP_REPORT_ONLY` | `false` | Report without blocking (safe rollout) |
| `ORWEMS_PASSWORD_EXPIRY_ENABLED` | `false` | Refuse sessions past the password age |
| `ORWEMS_PASSWORD_EXPIRY_DAYS` | `180` | The password lifetime, when enabled |
| `ORWEMS_TWO_FACTOR_ENABLED` | `false` | Reserved; honoured by nothing yet |

Read in full in `config/orwems.php` and `.env.example`, which are the
authoritative references — this table is a summary, not a second source.

## 3. Transport: HTTPS redirect and HSTS (opt-in)

`EnsureSecureTransport` (global middleware, `bootstrap/app.php`) redirects an
insecure request to the same path on `https://` with a 301 — but only when
`ORWEMS_FORCE_HTTPS=true`. Off by default, because a hard-coded redirect
would make `http://localhost` development impossible.

`Strict-Transport-Security` is sent by `ApplySecurityHeaders` only when
`ORWEMS_HSTS_ENABLED=true`, with the configured `max-age` and optional
`preload`. HSTS pins browsers to `https://` for the whole max-age window, so
sending it before the site is genuinely served over HTTPS would lock
operators out — that is why it is a deliberate production step, not a
default.

Deployment note: behind a TLS-terminating reverse proxy, the proxy must
forward `X-Forwarded-Proto` and be listed in `TRUSTED_PROXIES`, or every
request still looks insecure and redirects in a loop.

## 4. Response headers (unconditional)

`ApplySecurityHeaders` runs globally so every response is covered — including
redirects, error pages and the `/up` health probe, which are often the only
responses a browser sees before a decision is made. None of it touches the
session, the database or the user, so running before `StartSession` is safe.

Always sent: `X-Content-Type-Options: nosniff`,
`X-Frame-Options: SAMEORIGIN` (consistent with `frame-ancestors`),
`Referrer-Policy: strict-origin-when-cross-origin`,
`X-Permitted-Cross-Domain-Policies: none`, and a `Permissions-Policy` that
dismisses every device capability ORWeMS has no use for.

Content-Security-Policy is on by default (`ORWEMS_CSP_ENABLED=true`).
`script-src` stays strict — `'self'` only, plus the Vite dev origin while
`public/hot` exists, never `'unsafe-inline'` or `'unsafe-eval'`, with
`script-src-attr 'none'` blocking inline event handlers on top. The one
deliberate exception is `style-src 'unsafe-inline'`: the Bootstrap/Vite
templates use inline `style="…"` attributes throughout, and blocking them
would visibly break the UI. Report-only mode (`ORWEMS_CSP_REPORT_ONLY=true`)
evaluates and reports the policy without blocking — the safe way to roll a
tightened policy out.

## 5. Request correlation

`AssignRequestId` (first in the global stack, so the id exists before
anything else runs) gives every request a correlation identifier, echoes it
as `X-Request-Id`, and stamps it onto audit rows. An inbound id is honoured
only when it matches `[A-Za-z0-9._-]{8,64}`; anything else is discarded and
replaced with a UUID, so a caller cannot inject markup or an unbounded
string into the audit table. This is what ties a support report, an audit
row and an application-log line back to one concrete request.

## 6. Credential lifecycle

Strength: the reset and change flows share one rule — minimum 12 characters
with letters and numbers. Length is what actually matters; the character
rules stop a dictionary word of digits.

Change: `GET/POST change-password` (`AuthenticatedPasswordController`) lets
a signed-in operator replace their own password. The current password must
be supplied (`current_password:web`), the new one is hashed by the model
cast, `remember_token` is rotated, and `password_changed_at` is stamped.
Then **every** session for the account ends — including this one — and the
operator returns to sign in. If a password is being changed because it may
have been exposed, an attacker's live session must not survive it.

Expiry: opt-in. With `ORWEMS_PASSWORD_EXPIRY_ENABLED=true`, the
`password.fresh` middleware refuses any authenticated session whose password
is older than `ORWEMS_PASSWORD_EXPIRY_DAYS` and redirects to the change
screen. Accounts with a null `password_changed_at` are grandfathered, not
expired — the column is populated from the first reset or change, and
treating "unknown" as "expired" would lock every pre-existing account out
the moment the flag is flipped. The change-password routes and `logout` are
exempt, so the operator can always reach the fix and can always sign out.
Enabling expiry is an operational policy decision, not a code deployment;
with it off the middleware does nothing at all.

Middleware order on authenticated routes is load-bearing and written the
same way everywhere: `auth` (WHO) → `account.active` (WHETHER THEY MAY ACT)
→ `password.fresh` (WHETHER THEIR CREDENTIAL IS STILL CURRENT) →
`permission` (WHETHER THEY MAY DO THIS SPECIFIC THING). Aliases are applied
explicitly on the route, never via group injection, per the Phase 3A finding
documented in `bootstrap/app.php`. The change-password link in the topbar is
where an operator expects to find it — and it is the screen an expiry
redirect lands on.

## 7. The audit trail

One row per security-relevant event in `audit_logs`
(`2026_10_03_100000_create_audit_logs_table`). Columns: `user_id` (the
operator who caused the event, nullable — a guest or a console run has no
actor), `event` (a stable machine name from `App\Enums\SecurityEvent`),
polymorphic `subject` (for account-authorisation events, always the affected
account: `user_id` is who acted, `subject` is who was acted upon), request
forensics (`ip_address`, `user_agent`, `request_id`, `created_at`), and a
small JSON `context` for identifiers only.

The vocabulary is part of the system's contract: names are lowercase
`snake_case`, an existing name is never renamed or reused, new events are
added as new cases. Cases marked "reserved" (`permission_assignment`,
`two_factor_changed`) are NOT written by any code today — they exist so a
future phase does not invent a divergent name.

### The single writer

Nothing inserts into `audit_logs` except `App\Support\Audit\Auditor`, so
every call site gets three guarantees for free: request forensics attached
automatically (nulls in console context); credential material stripped —
forbidden key substrings dropped (with `programme_id`, `geography_id`,
`role_code` allow-listed as the grant-identifier vocabulary), bcrypt/argon
shapes dropped, arbitrary objects never serialised, strings capped at 500
chars and 30 entries; and loud failure — a write that fails throws rather
than leaving a silent gap in the trail.

### Append-only by construction

An audit trail that can be rewritten is a narrative, not evidence. Model
`update()`/`delete()`/`forceDelete()` throw `LogicException`;
`AuditLogBuilder` throws on bulk `update()`/`delete()` so the model guards
cannot be sidestepped; there is no `updated_at`; `user_id` is `RESTRICT` so
an account with a trail cannot be removed out from under it. There is no
route, controller or command that deletes audit rows, and retention — when it
comes — must be an explicit offline reviewed operation.

### What is recorded

Authentication (wired to framework events in `AppServiceProvider`, so future
sign-in paths are covered automatically): `login_success`, `login_failed`
(address only — never the attempted password; `subject` linked when an
account matched), `login_throttled`, `logout`, `password_reset_requested`
(identical response whether the address exists or not, so the endpoint
cannot enumerate staff addresses; the matched/not distinction lives only in
the trail), `password_reset`, `password_changed`, `account_disabled`
(recorded by `EnsureAccountIsActive` at refusal, before logout's own row).

Responsibility and reach (wired into `User::assignRole`,
`assignProgrammeScope`/`revokeProgrammeScope`,
`assignGeographicScope`/`revokeGeographicScope`): `role_assignment`,
`programme_scope_granted`/`_revoked`, `geographic_scope_granted`/`_revoked`
— each with actor, subject account and the grant identifier. Only actual
changes are recorded: idempotent re-grants and re-revocations write nothing,
because nothing happened; re-activating a withdrawn scope is a grant,
because reach was restored.

## 8. What Phase 3D does not do

- **Second factor.** `ORWEMS_TWO_FACTOR_ENABLED` defaults to false and no code
  honours it; a test asserts both. Enabling it activates nothing.
- **Audit administration UI.** There is no screen, route or command that
  lists or manages audit rows — the trail is evidence, not a work queue.
- **Retention/pruning.** Rows accumulate; archival is a future offline
  operation, never a request-scoped side effect.
- **Scope or permission grants.** This phase records responsibility and reach
  changes; it confers none.

## 9. Production checklist

1. Serve over HTTPS behind a TLS terminator forwarding `X-Forwarded-Proto`;
   list the proxy in `TRUSTED_PROXIES`.
2. Set `ORWEMS_FORCE_HTTPS=true`, then `ORWEMS_HSTS_ENABLED=true` (with a
   short `max-age` first, raising to 31536000 once stable).
3. Set `SESSION_SECURE_COOKIE=true` (see `.env.example`).
4. Decide the password-expiry policy with WICHOV before touching
   `ORWEMS_PASSWORD_EXPIRY_*` — the default (off) is the safe one.
5. Leave `ORWEMS_TWO_FACTOR_ENABLED=false` until a real second factor ships.
6. Never commit the production `.env`.

## 10. How to verify

`tests/Feature/Security/` pins every property above: `AuditTrailTest` (the
single writer, sanitisation, append-only enforcement at model and builder
level, forensics), `AuthenticationAuditTest` (every auth event),
`PasswordExpiryTest` (opt-in behaviour, grandfathering, the change flow),
`ScopeAssignmentAuditTest` (only-actual-changes auditing of roles and
scopes), `SecurityHeadersTest` (headers, CSP, HSTS opt-in, request-id trust
rules). `php artisan test tests/Feature/Security` is the command; each test
name states the property it protects, so a failure reads as a sentence about
what broke.
