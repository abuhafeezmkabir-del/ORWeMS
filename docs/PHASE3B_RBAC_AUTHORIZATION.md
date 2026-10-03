# ORWeMS Phase 3B — RBAC, Roles, Permissions & Authorization Foundation

> **Architectural rule (mandatory for Phase 3C):** roles answer **WHAT** an
> operator is responsible for and allowed to do. Access scopes will answer
> **WHERE** that responsibility applies. Therefore `role ≠ programme`,
> `role ≠ state`, `role ≠ LGA`, `role ≠ ward`, `role ≠ community`. No
> geographic or programme column exists on `roles`, `permissions`,
> `role_user` or `permission_role`, and none may be added — Phase 3C hangs
> scope off these rows rather than rewriting them.

## 1. Role model

Seven roles, stable machine `code`s (`super_administrator`,
`system_administrator`, `programme_manager`, `case_officer`,
`registration_officer`, `assessor`, `auditor`). A role names a job-function
responsibility: entry → verification, assessment → approval, and casework →
administration are held by different roles (separation of duties). `name` is a
display label and is never an authorization key. `is_active` withdraws a role
without destroying history. Evaluated and deliberately rejected: education /
health officers (no module yet), research analyst (needs de-identification
controls first), read-only viewer (duplicates auditor), welfare manager
(overlaps programme manager for the pilot). Recorded in `RolePermissionSeeder`.

## 2. Permission model

`resource.action` codes (`beneficiaries.view`, `assessments.approve`,
`users.assign_roles`, …) defined in `PermissionSeeder` (reference data only —
defining a code grants it to nobody). CRUD alone is not the vocabulary:
`verify`, `approve`, `close`, `archive`, `upload`, `export`, `disable` are
separate because they are separate duties. There are **no delete permissions**:
records archive, they are not destroyed. Only families mapping to something
ORWeMS has are defined; education/health/care families are added additively
when those modules are built. `code` is the only authorization key.

## 3. Naming convention

`snake_case` resource, dot, `snake_case` action: `beneficiaries.verify`,
`users.assign_roles`, `reports.export`, `system.maintenance`. Authority over
people (`users.assign_roles`) is separate from authority over privileges
(`roles.manage`) so one compromised account cannot grant itself anything.

## 4. Relationships

`User ↔ Role` via `role_user`; `Role ↔ Permission` via `permission_role`.
`User::roles()`, `Role::users()`, `Role::permissions()`,
`Permission::roles()`; `User::permissionCodes()` resolves the effective set
through both joins, ignoring inactive roles/permissions, de-duplicated.
`User::hasRole($code)`, `User::hasPermission($code)` (code, never name;
unknown code → `false`). `assignRole()` is idempotent
(`syncWithoutDetaching`) and reachable only from authorized code — never mass
assignment, never a request payload.

## 5. Authorization strategy

`AppServiceProvider` binds the vocabulary to Laravel's Gate (`Gate::before`):
every recognised code resolves through `hasPermission()`, so `$user->can()`,
`authorize()`, Blade `@can`, and the `permission:` middleware share one source
of truth. Unknown abilities return `null` so future policies
(`BeneficiaryPolicy::view`, …) keep working. `EnsureUserHasPermission`
(alias `permission`, explicit on routes — never `appendToGroup`, per the Phase
3A middleware finding) answers whether the operator may do this specific
thing; misspelled codes throw `LogicException` (fail loud, not silent deny).
`PermissionCatalog` supplies the recognised-code list (short-TTL cache,
bypassed in testing, flushed on seeding).

## 6. Denial behaviour

Deny by default, structurally: the permission set is built by joining real
granted rows, so an ungranted capability is simply absent — no fallback branch
can allow by omission. Guests → `auth` redirects to login. Authenticated but
unauthorized → `AuthorizationException` → **403 Forbidden**, never a redirect
that hides the failure.

## 7. Disabled accounts

`account.active` (Phase 3A) precedes `permission` on every administration
route. A disabled operator's session is revoked before authorization is
consulted, so retained `role_user` rows confer nothing. Disabling never
deletes assignments — the record of who held what survives.

## 8. Future scope separation

Phase 3C adds scope tables (programme/state/LGA/ward/community) referencing
users and/or role assignments; `roles` stays place-free so one role serves
every state. Future policies consume `hasPermission()` plus scope checks;
sensitive-domain families (`health.*`, `education.*`) are added as ordinary
permission rows. Audit logging, 2FA, and domain CRUD are explicitly out of
scope (Phase 3D / later phases).

## 9. Security decisions

Server-side enforcement only (middleware + controller + DB constraints; hidden
menu items are usability, never control). No `$user->id === 1` checks, no
`role === 'admin'` string checks, no public registration, no self-assignment
(controller refuses `user_id` === requester), inactive roles unassignable,
`is_active`/`status` never mass-assignable, state-changing assignment is
POST+CSRF (never GET), RESTRICT on all RBAC foreign keys (no silent cascades),
no blanket researcher access, export separated from view.

## 10. Deferred

Phase 3C: programme/geographic scope tables, scope middleware, scope policies,
research de-identification. Phase 3D: audit log, 2FA, security headers,
password expiry. Later domain phases: beneficiary/household/assessment/need/
intervention/education/health/care CRUD with `BeneficiaryPolicy`-style
policies built on this foundation.
