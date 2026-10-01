# ORWeMS — Phase 3A Authentication & User Foundation

**Project:** ORWeMS (Orphans Registration and Welfare Management System)
**Client:** WICHOV — Welfare Initiative for Children, Orphans and Vulnerable
**Phase:** 3A — Authentication & User Foundation
**Previous:** Phase 2.1 database integrity hardening

This document records what Phase 3A built, why each decision was made, and
what is deliberately left for later phases. It is a practical reference, not a
specification.

---

## 1. Starting point

Phase 2.1 left the application with Laravel's **default** authentication
skeleton and no authentication of its own:

| Present | Absent |
|---|---|
| `users` table (Laravel default columns) | Any auth route or controller |
| `password_reset_tokens` table | Any sign-in/sign-out flow |
| `sessions` table (database driver) | Any middleware protecting a page |
| `config/auth.php` (session guard, password broker) | Any Blade auth view |
| `App\Models\User` (default) | Account status, audit timestamps |

**No authentication package was installed** — no Breeze, Fortify, Jetstream or
Sanctum. `composer.json` requires only `laravel/framework` and `laravel/tinker`.
Everything in Phase 3A is built on the framework itself, so there is no new
dependency, which keeps the cPanel deployment as simple as before.

---

## 2. Identity decision: email only

ORWeMS signs operators in with their **email address**. No username column was
added.

* `users.email` is already unique in the schema, so a username would have been a
  second identifier to invent, secure, remember and enforce.
* WICHOV staff each have an address, and email is also the delivery channel for
  password resets — one identity serves both.
* Adding a username later is a cheap additive change.

**Normalisation.** `User::email()` lowercases and trims on write. MySQL's
default collation is already case-insensitive, but the SQLite test database is
not; normalising at the model keeps both engines behaving identically.

---

## 3. Database changes

Migration: **`2026_09_29_200000_add_authentication_fields_to_users_table`**
(batch 4). Three columns and one index; nothing was dropped or rewritten, and
the pre-existing dev account was preserved.

| Column | Type | Purpose in Phase 3A |
|---|---|---|
| `status` | `varchar(20)` NOT NULL default `'active'` | Account lifecycle. Indexed for account review and Phase 3B administration. |
| `password_changed_at` | timestamp NULL | Stamped on a successful reset, so a credential change is reviewable. |
| `last_login_at` | timestamp NULL | When the account last authenticated. |

Existing columns (`email_verified_at`, `remember_token`, timestamps) were kept
because they are part of Laravel's authentication contract.

**What was deliberately not added:**

* `username` — see §2.
* A failed-login counter — Laravel's rate limiter blocks brute force without
  writing anything to the database, which is better for a system that must
  minimise stored personal data.
* A login IP column — an operator's IP is personal data; `last_login_at` gives
  the accountability signal without collecting it.
* Role or permission columns — Phase 3B.
* `created_by` / `updated_by` / `verified_by` on domain tables — see §9.

---

## 4. Routes

Defined in `routes/auth.php`, loaded by the `then:` callback in
`bootstrap/app.php`.

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/login` | `login` | `guest` |
| POST | `/login` | `login.store` | `guest`, `throttle:10,1` |
| GET | `/forgot-password` | `password.request` | `guest` |
| POST | `/forgot-password` | `password.email` | `guest` |
| GET | `/reset-password/{token}` | `password.reset` | `guest` |
| POST | `/reset-password` | `password.store` | `guest` |
| POST | `/logout` | `logout` | `auth`, `account.active` |
| GET | `/dashboard` | `dashboard` | `auth`, `account.active` |

There is **no registration route**, and a test asserts `/register` returns 404.
ORWeMS is a controlled system: accounts are provisioned by an administrator
(Phase 3B). Sign-out is POST-only so it cannot be triggered by a link, a
browser prefetch, or a third-party page.

---

## 5. Middleware, and one trap worth knowing

`EnsureAccountIsActive` is the only custom middleware. It exists because
Laravel's `auth` middleware asks only "is someone logged in?" — it has no
concept of account state, so an operator whose access was revoked would keep
working until their session expired. For a system holding children's records,
revocation must take effect immediately.

It is applied as an **explicit alias** (`account.active`) alongside `auth` on
every authenticated route. It was originally registered with
`Middleware::appendToGroup()`, which fails silently here for two reasons:

1. `Illuminate\Routing\MiddlewareNameResolver::resolve()` checks middleware
   **groups before aliases**, so registering a group named `auth` shadows the
   `auth` alias and `Illuminate\Auth\Middleware\Authenticate` stops running —
   the dashboard became publicly reachable. This was caught by the test suite.
2. In this Laravel 12 skeleton, `appendToGroup()` updates the HTTP Kernel's
   groups but not the Router's, which is what actually resolves route
   middleware names — so the middleware is never invoked at all.

**Rule for Phase 3B:** wrap all authenticated routes in one group carrying
`['auth', 'account.active']`. Never create a middleware group named `auth`.

---

## 6. Account status

`App\Enums\UserStatus` has exactly two states:

| State | Meaning |
|---|---|
| `active` | May authenticate and use the authenticated area. |
| `disabled` | Offboarded or access revoked. May not authenticate. |

Two states, not more: a larger state machine would be speculative, and no
workflow yet distinguishes a temporary hold from a permanent revocation.

An enum cast rather than a boolean, for two reasons: it matches every other
ORWeMS status column (`BeneficiaryStatus`, `NeedStatus`, …), and a third state
such as `suspended` can be added **later without a migration** by adding an enum
case.

**Enforcement is in the authentication layer, not the UI**, and in two places:

1. **At sign-in** — status is folded into the credential query in
   `LoginRequest::authenticate()`. This is deliberate: a disabled account then
   produces *exactly* the same generic failure as a wrong password, so status
   cannot be probed.
2. **On every authenticated request** — `EnsureAccountIsActive` terminates a
   session belonging to an account disabled mid-session.

`status` is **not mass-assignable**, so it can never be changed by a request
payload; reactivation is always an explicit `forceFill` in administrative code.

---

## 7. Session security

| Control | Setting |
|---|---|
| Driver | `database` (`.env.example`) |
| Idle lifetime | 120 minutes |
| "Remember me" | **Not offered.** No persistent long-lived session is created. |
| Regeneration on sign-in | Yes — `Auth::attempt()` migrates the session, plus an explicit `regenerate()` |
| Invalidation on sign-out | Yes — `logout()`, `invalidate()`, `regenerateToken()` |
| Cookie flags | `http_only=true`, `same_site=lax` |
| Secure cookie | `SESSION_SECURE_COOKIE`, left **unset** so local HTTP development still works; set to `true` on the HTTPS deployment |
| Payload encryption | `SESSION_ENCRYPT=true` in `.env.example` — the session payload lives in the database and carries staff identity |

Nothing is hard-coded for HTTPS, so plain-HTTP localhost development keeps
working, and nothing is hard-coded for development either.

---

## 8. Password reset

Built on Laravel's own broker (`password_reset_tokens`, already present). No
custom token protocol, no custom cryptography.

* Link expires after **60 minutes** (`AUTH_PASSWORD_RESET_EXPIRE`).
* Regeneration is throttled to one per **60 seconds** per account/IP
  (`AUTH_PASSWORD_RESET_THROTTLE`), so the endpoint cannot flood an inbox.
* Tokens are single-use: deleted the moment the password is replaced.
* New password must be **≥ 12 characters** with letters and numbers, confirmed.
* The password is hashed by the model's `hashed` cast — never stored in
  plaintext.
* `remember_token` is rotated, invalidating any outstanding remember cookie.
* On success, `PasswordReset` is dispatched (see the note below), which stamps
  `password_changed_at` and **destroys the account's other database sessions** —
  if a password was changed because it may have been exposed, an attacker's
  live session must not survive it.

> **Framework note.** Laravel 12's `PasswordBroker` performs the token deletion
> but **no longer dispatches `Illuminate\Auth\Events\PasswordReset`**.
> `NewPasswordController` therefore raises the event explicitly after a
> successful reset. This was found by test, not assumed.

**User enumeration:** the response is identical whether or not the address
belongs to an account. Nothing is logged, and internal exceptions are never
shown to users.

**Environment-dependent limitation:** mail delivery depends on `MAIL_*`. With
the default `log` mailer the reset link is written to `storage/logs` instead of
being sent. A real transport must be configured before password resets are
needed in production; ORWeMS does not fabricate a provider.

---

## 9. Audit readiness and what is deferred

Phase 3A establishes the identity these future fields will point at, but does
not add them. Each is a domain change belonging to the module that introduces
the action:

| Future field | Belongs to |
|---|---|
| `beneficiaries.created_by` | Beneficiary registration module |
| `*.updated_by` | Wherever records become editable |
| `assessments.assessor_id`, `verified_by` | Assessment module (the Phase 2 `assessor_name` is a free-text placeholder) |
| `interventions.created_by` | Intervention module |
| `documents.uploaded_by` | **Already present** (FK to `users`, Phase 2) |
| Audit/activity log table | Phase 3D / later |

`last_login_at` and `password_changed_at` are the only accountability data
Phase 3A stores.

**Email verification is deferred.** Staff are provisioned by an administrator
(Phase 3B) and there is no public registration, so verification would add
friction without adding security. `email_verified_at` is retained and `User`
does not implement `MustVerifyEmail`; enabling it later is a one-line model
change plus `verified` middleware on the right routes.

---

## 10. Test environment

Tests run on SQLite in memory (`phpunit.xml`), against MySQL 8.4.3 in
development and production. Three Phase 3A behaviours depend on the driver or
the container, and each is handled explicitly rather than hidden:

| Behaviour | Note |
|---|---|
| Email case-insensitivity | Normalised in the model so SQLite and MySQL agree. |
| Terminating sessions on reset | Only meaningful with the `database` driver, so that test sets `config(['session.driver' => 'database'])`; a companion test proves the reset still succeeds with the `array` driver. |
| Disabling a live session | The test container caches one guard instance across simulated requests, so `forgetGuards()` is called after the change to reproduce real per-request behaviour. |

No MySQL-specific protection was removed to satisfy SQLite.

---

## 11. Deployment checklist

1. `php artisan migrate` — applies the `users` migration (additive only).
2. Set `SESSION_SECURE_COOKIE=true` once the site is served over HTTPS.
3. Set a real mail transport (`MAIL_*`) before password resets are needed.
4. Create the first operator account through the console — never seed a WICHOV
   administrator into production. `test@example.com` is only ever created when
   the environment is `local` or `testing`.
5. `php artisan config:cache` / `route:cache` are safe: no closures are used in
   routes.

---

## 12. Deliberately deferred

**Phase 3B — RBAC:** roles, permissions, policies, user administration CRUD,
role assignment, and the `account.active` group convention across app routes.

**Phase 3C — scope:** programme access scope, geographic access scope.

**Phase 3D — hardening:** audit log, 2FA / MFA, password expiry policy,
security headers, rate-limit tuning, penetration testing, CSP.

**Not planned unless required:** API or mobile authentication (no Sanctum has
been installed), and audit-ready columns on domain tables, which belong with
their modules.
