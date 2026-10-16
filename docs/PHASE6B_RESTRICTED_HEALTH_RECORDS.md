# ORWeMS Phase 6B — Restricted Health Records Foundation

> **Architectural rule:** Phase 6A answered *where does this child study*. This
> phase answers the third case-file question — **WHAT HEALTH CONCERNS HAS THIS
> CHILD BEEN FOLLOWED FOR, SINCE WHEN, AND WHAT HAPPENED NEXT** — under a
> stricter posture than any previous phase, because health content is the most
> sensitive data ORWeMS will ever hold. It stays a **casework-level** record
> (what was observed, what action is owed), never a clinical one. Authorization
> is the same permission-then-scope chain as Phases 3–5, but the *least-privilege
> role mapping* and the *privacy rendering rules* are narrower here on purpose:
> an operator without health permission does not merely lose buttons — they see
> a single neutral line, and not even a count reaches Blade.

## 1. What this phase is

One workflow anchored to the beneficiary case file: **health records** — the
append-only history of a child's casework-level health concerns. A record names
a configurable concern (`health_concerns` reference row), a one-line summary,
the record date, an optional referral target and follow-up status, and notes. It
is opened, at most amended while it is **open**, and then **closed** with an end
date and a closure reason. History is evidence: there is no `DELETE` route and
no `destroy` method anywhere — a `DELETE` request to a health URL answers
**405 Method Not Allowed**, and the row remains.

"Open" is derived, exactly like Phase 6A's "current": **`end_date IS NULL`**,
never a stored status that could drift. There is deliberately **no status
column and no status enum** (see §3 for how this differs from 6A).

Deliberately **not** in this phase: diagnoses, prescriptions, medications, lab
results, biometric values, attachments, or any clinical vocabulary. Each such
column is a new privacy surface; adding one later is an additive migration plus
review, not a schema we walk back.

## 2. Schema & what is deliberately absent

Two tables in one migration (`2026_10_08_130000_create_health_tables.php`),
mirroring the shape Phase 6A established:

- **`health_concerns`** — the configurable vocabulary (`code` unique, `name`,
  `sort_order`, `description`, `is_active`). Kept as reference data rather than
  a PHP enum for the same reason education levels are a table: programme staff
  configure it, and deactivating a concern must not erase history that cites it.
- **`health_records`** — the history table. Key columns and decisions:
  - `beneficiary_id` and `health_concern_id` are both **`restrictOnDelete`** —
    neither a child nor a concern a record cites can vanish beneath it.
  - `summary` (500 chars, NOT NULL — a record must say *something*),
    `record_date`, nullable `end_date`, nullable `closure_reason`,
    `follow_up_status` (free string, default `none` — not an enum: follow-up
    vocabulary is casework practice, not schema), nullable `referral_to`,
    nullable `notes`.
  - A MySQL `CHECK (end_date IS NULL OR end_date >= record_date)` refuses a
    closure that predates the record; the model guard produces the same rule as
    a readable validation message on SQLite, which cannot add `CHECK`
    constraints at runtime.
  - The exact-open-duplicate `UNIQUE` and the history index — see §3.

**Absent on purpose:** no diagnosis/prescription/lab/biometric columns; no
`status` column; no single-open-per-beneficiary invariant (see §3); no soft
deletes on health rows themselves — the child's *archival* (soft delete) is
what freezes writes, and history rows persist as evidence.

`beneficiary_id`, `end_date` and `closure_reason` are **not mass-assignable**:
the beneficiary is set by the relation (`$beneficiary->healthRecords()->create()`)
and closure fields are written only by the close flow, which also holds the
audit call.

## 3. The exact-open-duplicate invariant — three layers, narrower than 6A

Health is **narrower than education in one way and wider in another**. Wider:
unlike an enrolment, a child can legitimately carry **several open records at
once for *different* concerns** — a wound being followed while a nutrition
concern is also open is real casework, and a single-current UNIQUE would forbid
it (a test, `test_different_concerns_may_both_be_open`, pins this). Narrower:
the **exact duplicate** — same beneficiary + same concern + still open — is
refused, because two concurrent open rows for the *same* concern are a data
mistake, not casework.

| Layer | Mechanism | Why it exists |
| --- | --- | --- |
| Form request | `StoreHealthRecordRequest` / `UpdateHealthRecordRequest` cross-field check (concern in use, validated `exists` on active concerns) | Refuses the duplicate with a **readable field error** naming the concern, before any write |
| Model | `HealthRecord::booted()` `saving` guard (`beneficiary_id` + `health_concern_id` + `end_date IS NULL`) | Same readable message for direct model writes (tinker, console) and on drivers without generated columns |
| Database | `UNIQUE (beneficiary_id, health_concern_id, open_flag)` where `open_flag` is a **virtual generated column** = `CASE WHEN end_date IS NULL THEN 1 END` | The **hard stop that cannot be bypassed**. UNIQUE ignores NULLs, so any number of *closed* rows coexist per concern; only one row per child+concern can hold `open_flag = 1`. This catches the race two operators slip past the model guard |

Because the UNIQUE only guards `open_flag`, a second row for the same concern
becomes possible the instant the first closes — which is correct: close and
re-open is legitimate follow-up, so no single-current invariant is layered on.

## 4. Authorization architecture (same chain, deliberately tighter mapping)

`HealthRecordPolicy` is the same **PERMISSION first, then SCOPE** two-step as
`EducationEnrolmentPolicy` and `PlacementPolicy`, kept as two visible
conjunctions so a reviewer can see neither layer alone grants. Scope is always
the scope of the record's **beneficiary** — health history is case content of
the child it describes.

| Ability | Permission required | Scope | Extra lifecycle guard |
| --- | --- | --- | --- |
| `viewFor(Beneficiary)` | `health_records.view` | beneficiary in scope | — (case-file card + index gate) |
| `viewAny()` | `health_records.view` | — | coarse parity check; a beneficiary is always the context |
| `view(HealthRecord)` | `health_records.view` | beneficiary in scope | — (record page, writes `HealthRecordViewed`) |
| `create(Beneficiary)` | `health_records.create` | beneficiary in scope | **archived beneficiary refused outright** |
| `update(HealthRecord)` | `health_records.update` | beneficiary in scope | only while **open**; a closed record is never rewritten |
| `close(HealthRecord)` | `health_records.close` | beneficiary in scope | only while **open**; closing is not idempotent |

Four permissions seeded (`health_records.view/create/update/close`), bringing
the total to **54** (50 from Phases 0–6A). `close` is separated from `update`
for the same reason as placements and education: whoever runs day-to-day
casework cannot also decide when a concern ended.

**Role mapping** (`RolePermissionSeeder`) — least-privilege, and *narrower than
6A deliberately*: a **case officer** holds all four (end-to-end casework); a
**programme manager** holds `view` + `close` but **not** `create` or `update`
(oversight without authoring or amending health content). A **registration
officer**, **intake officer**, **assessment officer**, **auditor** and
**administrator** hold **none** — health history is not routine intake,
assessment, oversight or platform-administration input; no role was created for
this phase, and admin holds no health permission because platform
administration is not clinical authority.

**Route middleware → policy → form request → model + DB**, link by link:

1. Every route carries `permission:health_records.*` plus `auth`,
   `account.active`, `password.fresh`.
2. The controller re-authorizes through the policy (`viewFor` / `view` /
   `create` / `update` / `close`), applying programme AND geographic scope on
   the beneficiary.
3. Form requests validate input — including duplicate-open, referral
   coherence, date inversion and `concern exists and is active` — so denials
   name the offending field instead of being a bare 403.
4. Model guard + generated-column UNIQUE are the final, unbypassable layer.

**Cross-record URLs are a 404, not a 403** (`assertRecordBelongsTo`): a record
id resolved under the wrong beneficiary's URL answers 404 — a 403 would confirm
the id exists. This holds even for a sibling child fully within the operator's
scope. Archived children: routes run `->withTrashed()` so the case file and
history stay viewable, while `create` / `update` / `close` all refuse writes.

## 5. Case-file privacy integration (the row-never-reaches-Blade rule)

`BeneficiaryController::careContext()` loads health rows **only** for operators
the policy answers yes to — the same lazy shape as placements and education,
but the fallback is different. Where education simply omits the card, health
renders an explicit **neutral line** for everyone else:

```php
if (Gate::forUser($user)->allows('viewFor', [HealthRecord::class, $beneficiary])) {
    // rows loaded, passed under healthRecords / openHealthRecords keys
}
// else: nothing is loaded — the Blade @else branch renders
// "Health information is restricted." and nothing else.
```

The privacy contract the `@isset` / `@else` pair enforces:

- **No rows before the policy speaks.** Health rows are never loaded "to hide
  them later" — an unauthorized case file performs zero health queries.
- **No counts, no summaries, no referral detail.** The restricted branch shows
  one muted line inside a card titled "Health records / Restricted". A count
  would itself disclose how much health history exists; the design refuses
  even that.
- **Authorized rendering** lists each record's concern, summary, dates, follow-up
  and referral — but only after `viewFor` passed, on a page whose own audit
  (`HealthRecordViewed`) fires on the record page.

`test_the_case_file_leaks_no_count_no_summary_no_referral` and
`test_audit_metadata_carries_identifiers_only` pin these rules with marker
strings (`SENSITIVE_GRAPH_MARKER`, `SENSITIVE_GRAPH_REFERRAL`) that must never
appear in an unauthorized response.

## 6. Audit privacy contract

Four `SecurityEvent` cases added, written through the existing
`Auditor::record(...)` architecture:

- `health_record_created` — a new record opened.
- `health_record_updated` — an OPEN record amended (never a closed one).
- `health_record_viewed` — the record page viewed (viewing the case-file
  *section* is covered by the page's own access pattern; the dedicated view
  event fires on `show`).
- `health_record_closed` — closed with an end date; the history row remains.

**What is audited vs. what never is.** Context carries identifiers only —
`beneficiary_ref`, `health_record_id`, `health_concern_id`,
`follow_up_status`. The `summary`, `referral_to` and `notes` free text **never
enter an audit row**: audit logs are widely readable operational metadata, and
transcribing health narrative into them would defeat the access restrictions
this phase exists to establish. Same non-sensitive-serialization contract as
Phase 3D.

## 7. Seeding

`HealthConcernSeeder` (wired into `DatabaseSeeder`) seeds a **seven-row**
concern vocabulary idempotently by construction: `updateOrCreate` keyed on
`code`, so re-seeding converges rather than duplicates, and a concern an
administrator has deactivated (`is_active = false`) **stays deactivated** —
which the store validation honors by rejecting deactivated concerns while
leaving historical citations intact.

Verified against the MySQL 8.4 runtime: `php artisan db:seed --force` run
repeatedly converges at **54 permissions** (50 prior + 4 health) and **7
concerns**, with role mappings unchanged for every pre-existing role.

## 8. Routes

All eight routes are nested under each beneficiary
(`beneficiaries/{beneficiary:beneficiary_ref}/health`, name prefix
`beneficiaries.`) because a health record **is** case content of the child it
describes. There is deliberately **no DELETE route**:

| Method | URI (relative) | Name | Permission |
| --- | --- | --- | --- |
| GET | `…/health` | `beneficiaries.health.index` | `health_records.view` |
| GET | `…/health/create` | `beneficiaries.health.create` | `health_records.create` |
| POST | `…/health` | `beneficiaries.health.store` | `health_records.create` |
| GET | `…/health/{healthRecord}` | `beneficiaries.health.show` | `health_records.view` |
| GET | `…/health/{healthRecord}/edit` | `beneficiaries.health.edit` | `health_records.update` |
| PUT | `…/health/{healthRecord}` | `beneficiaries.health.update` | `health_records.update` |
| GET | `…/health/{healthRecord}/close` | `beneficiaries.health.close` | `health_records.close` |
| POST | `…/health/{healthRecord}/close` | `beneficiaries.health.close.confirm` | `health_records.close` |

All eight run with `->withTrashed()` (history stays viewable for an archived
child; writes are refused by the policy instead). Verified via
`php artisan route:list --path=health` — 8 routes, correct
`permission:health_records.*` middleware on each, no DELETE verb anywhere.

## 9. Tests

`tests/Feature/Health/` — five files, 23 tests, sharing `BuildsHealthFixtures`:

- **`HealthAuthorizationTest`** — guest redirect, disabled-account refusal,
  permission-without-scope denial, `beneficiaries.view` alone insufficient,
  case officer may record (and it is audited), manager may view+close but not
  create, archived child gets no new record, cross-child id is 404,
  destructive deletion unavailable (405), and the case file leaks no
  count/summary/referral.
- **`HealthWorkflowTest`** — valid creation, future record date rejected,
  deactivated concern rejected, exact open duplicate refused, *different*
  concerns may both be open, close preserves the record and is audited, a
  closed record refuses amendment and a second close.
- **`HealthScopeTest`** — cross-state and cross-programme isolation on both
  geographic dimensions (either alone refuses, together grant), record-level
  isolation between two in-scope children.
- **`HealthPrivacyTest`** — unauthorized case file shows only the neutral line
  (marker strings absent), audit metadata carries identifiers only (summary /
  referral / notes never appear), forged `actor_id` / `beneficiary_id` /
  scope fields cannot override server-side derivation.
- **`BuildsHealthFixtures`** — shared fixtures: scoped operators per role,
  two-programme/two-state geography, seeded concerns, marker-bearing records.

Full suite at commit: **395 passed, 6 skipped, 1576 assertions** (SQLite
in-memory via `phpunit.xml`; migration, seeding and route checks separately
verified on MySQL 8.4 — see §7 and §8).

## 10. Deliberate non-goals

- **No clinical content** — no diagnosis, prescription, medication, lab,
  biometric or attachment columns; this is a casework record, not an EMR.
- **No single-open-per-beneficiary invariant** — several *different* concerns
  may be open concurrently; only the exact duplicate is refused (§3).
- **No status enum** — openness is derived from `end_date IS NULL`, exactly as
  6A derives currency.
- **No deletion path** — no `DELETE` route, no `destroy` method, no health
  `forceDeleted` handling; closure is the terminal operation.
- **No health-specific role** — permissions map onto existing roles only,
  least-privilege (§4); admin deliberately holds none.
- **No cross-entity health surface** — health records attach only to a
  beneficiary; there is no household, programme or standalone health listing
  route, and no export/print path.
