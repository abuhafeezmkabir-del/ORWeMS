# ORWeMS Phase 6A — Education Management Foundation

> **Architectural rule:** Phases 3B and 3C answer *what* an operator may do
> and *where* that authority applies. Phase 4 built the first domain policy
> over a beneficiary. Phase 5 answered **where is this child living, with whom,
> and since when**. Phase 6A answers the sibling question the case file also
> exists for: **WHERE DOES THIS CHILD STUDY, AT WHAT LEVEL, AND SINCE WHEN**.
> Education history is evidence, so the terminal operation on an enrolment is a
> dated, reasoned **closure**, never a deletion. Nothing in this phase grants a
> capability or widens a scope; it can only record, amend-while-current, and
> close, and it refuses everything else through the same
> permission-then-scope chain as Phases 4 and 5.

## 1. What this phase is

One workflow anchored to the beneficiary case file built in Phase 4:

**Education enrolments** — the normalized, append-only history of where a child
has studied. An enrolment records the school (always by name, optionally linked
to a structured `institution` row), the education level (a configurable
reference), the class or year of study, and the start date. It is opened, at
most amended while it is the *current* one, and then **closed** by setting an
end date. There is no delete route and no destroy method anywhere — a `DELETE`
request to an enrolment URL returns **405 Method Not Allowed**, and the row
remains.

A single compound action — **Change Enrolment** — closes the current enrolment
and opens the next in **one transaction**, because a child is enrolled in one
place at a time and a two-request workflow would leave a window where the child
appears to have no current enrolment at all.

Deliberately **not** in this phase: school (institution) CRUD as a first-class
surface, health records, or any deletion path for education history. The
`institutions` table exists so an enrolment *may* link to a structured school,
but it is left **unseeded** and has no administrative route yet — see §5.

## 2. "Current" is derived, never stored

The single most important invariant: **a child has at most one current
enrolment**, and "current" means exactly `end_date IS NULL`.

There is deliberately **no status column and no status enum**. A stored status
would be a second source of truth that could contradict the dates; a
*configurable* status vocabulary would let an administrator configure two rows
as "current" — the very contradiction the invariant exists to prevent. The
invariant is therefore **structural**, not vocabulary:

| Layer | Mechanism | Why it exists |
| --- | --- | --- |
| Form request | `StoreEducationRequest::assertNoCurrentEnrolment()` | Refuses a second open row with a **readable field error** that names the Change flow, before any write |
| Model | `EducationEnrolment::booted()` `saving` guard | Same readable message for direct model writes (tinker, console) and on drivers without generated columns |
| Database | `UNIQUE (beneficiary_id, active_flag)` where `active_flag` is a **virtual generated column** = `CASE WHEN end_date IS NULL THEN 1 END` | The **hard stop that cannot be bypassed**. UNIQUE ignores NULLs, so any number of *closed* rows coexist; only one row can hold `active_flag = 1`. This is what catches a race two operators slip past the model guard |

Verified against **SQLite 3.39** (the test driver) and **MySQL 8.4** (the
Laragon runtime). Dates cannot invert either: a MySQL `CHECK
(end_date IS NULL OR end_date >= start_date)` refuses a closure before the
start; the model guard produces the same rule as a validation message on
SQLite, which cannot add a `CHECK` to an existing table.

## 3. The migration (one, additive)

`2026_10_08_120000_create_education_tables` creates exactly three tables and
re-runs cleanly:

- **`education_levels`** — configurable reference data (early childhood,
  primary, junior secondary, senior secondary, tertiary, vocational,
  non-formal). A seeded, flag-disabled table rather than an enum column — the
  "configurable reference data … rather than hard-coding business rules in
  enums" the brief asks for. `code` is unique; `is_active` withdraws a level
  from new selection without touching enrolments that already carry it.
- **`institutions`** — an OPTIONAL structured reference to a school. Left
  **unseeded**: institutions are real-world places a programme works with, and
  seeding them would fabricate programme data (see §5).
- **`education_enrolments`** — the history table. Key columns and decisions:
  - `education_level_id` is `restrictOnDelete` — a level a child studied at
    cannot vanish under the enrolment.
  - `institution_id` is **nullable** and `nullOnDelete` — removing an
    institution removes the *link*, never the history row.
  - `institution_name` is **NOT NULL** and survives independently of the
    optional link, so renaming or removing an institution row can never erase
    what a caseworker recorded about a child's schooling. **The recorded name is
    the source of truth; the structured link is an enrichment.**
  - `class_or_year` is free text on purpose — education systems name the
    class/grade differently, and a closed vocabulary would not fit every
    context.
  - `closure_reason` (written only by the close/change flows) and `notes`.
  - Two indexes: the single-active `UNIQUE` above, and
    `(beneficiary_id, start_date)` for history rendering.

`beneficiary_id`, `end_date` and `closure_reason` are **not mass-assignable**:
the beneficiary is set by the relation that creates the row
(`$beneficiary->enrolments()->create()`), and the end date / closure reason are
written only by the close and change flows, which also hold the audit call.

## 4. Authorization architecture (unchanged from Phases 3–5, reused in full)

`EducationEnrolmentPolicy` is the same two-step shape as `PlacementPolicy`:
**PERMISSION first, then SCOPE**, kept as two visible conjunctions rather than
one helper so a reviewer can see that neither layer alone grants. The scope is
always the scope of the enrolment's **beneficiary**: education history is case
content of the child it describes, and it follows the child.

| Ability | Permission required | Scope | Extra lifecycle guard |
| --- | --- | --- | --- |
| `viewFor(Beneficiary)` | `education.view` | beneficiary in scope | — (used by the case-file card and the index) |
| `viewAny()` | `education.view` | — | coarse parity check only; a beneficiary is always the context |
| `create(Beneficiary)` | `education.create` | beneficiary in scope | **archived beneficiary is refused outright** |
| `update(Enrolment)` | `education.update` | beneficiary in scope | only while **current**; a closed enrolment is never rewritten |
| `close(Enrolment)` | `education.close` | beneficiary in scope | only while **current**; closing is not idempotent |

Four permissions are seeded (`education.view`, `education.create`,
`education.update`, `education.close`). `close` exists for the same reason it
does for placements: whoever runs the day-to-day case cannot also decide when a
child left a school without a distinct capability existing.

**Role mapping** (`RolePermissionSeeder`) — no separate `education_officer`
role was seeded, deliberately: the four permissions are mapped onto the roles
that already need them, and a separate role would duplicate `case_officer`'s
education reach. A **case officer** holds all four (end-to-end casework); a
**programme manager** holds `education.view` and `education.close` but **not**
`education.create` (closing is the consequential oversight capability); an
**auditor**, **registration officer** and **assessor** hold `education.view`
only (history is standard assessment and oversight input).

**Route middleware → policy → form request → model + DB**, link by link:

1. Every route is gated by `permission:education.*` middleware and requires an
   active, freshly-authenticated account (`auth`, `account.active`,
   `password.fresh`).
2. The controller re-authorizes through the policy (`viewFor` / `create` /
   `update` / `close`), which applies **programme AND geographic** scope on the
   beneficiary.
3. The form request validates input — including the duplicate-current check, so
   a denial names the offending field instead of being a bare 403.
4. Model guards + database constraints are the final, unbypassable layer.

**Forged fields and mass assignment.** There is no household/caregiver scope
check in the education requests, unlike Phase 5's placement requests: an
enrolment references only the beneficiary (whose scope the policy already
enforces) plus **global reference rows**, not any scoped record a form could
smuggle in. The actor and the beneficiary are **derived server-side**
(`Auditor::currentActor()` and the route's `beneficiary`), never read from the
request, and `beneficiary_id` / `end_date` / `closure_reason` are not
fillable. A test (`forged_actor_and_beneficiary_fields_are_derived_server_side`)
proves a submitted `beneficiary_id` / `actor_id` cannot redirect the write.

**Cross-record URLs are a 404, not a 403.** An enrolment id resolved under the
wrong beneficiary's URL (`assertEnrolmentBelongsTo`) answers 404, because a
cross-record id is a mistake or an attempt, and a 403 would confirm the id
exists. This holds even for a sibling child fully within the operator's scope.

**Archived beneficiaries.** `create`, `update` and `close` all refuse an
archived (soft-deleted) beneficiary — new or amended education records on a
frozen case file would contradict what archiving means. Routes run
`->withTrashed()` so the case file and history remain *viewable* for an
archived child, but every write is refused by the policy.



## 5. Case-file integration

`BeneficiaryController::show` → `careContext()` loads the enrolments **only**
for operators whose permissions and scope reach them, exactly like placements:

```php
if (Gate::forUser($user)->allows('viewFor', [EducationEnrolment::class, $beneficiary])) {
    $enrolments = $beneficiary->enrolments()
        ->with(['educationLevel:id,code,name,sort_order', 'institution:id,code,name'])
        ->get();

    $data['enrolments'] = $enrolments;
    $data['currentEnrolment'] = $enrolments->first(
        fn (EducationEnrolment $enrolment): bool => $enrolment->isCurrent()
    );
}
```

The section is passed under the **`enrolments`** key, and the Blade view
(`beneficiaries/show.blade.php`) renders the **Education history** card inside
an `@isset($enrolments)` guard so an operator without `education.view` simply
sees a case file without the card — the rows are never loaded "to hide them
later". An **empty** education history renders a single muted line ("No
education records have been recorded for this child."); a populated one lists
each enrolment with the school name, level, start date, and — for a closed
enrolment — its end date, with a **Current** badge on the open row. The card
also links to the full history and, permission-gated, the record-enrolment
action. The card sits between the Placement history card (Phase 5) and the
Family & guardians card, so the case file reads: details → placement →
education → family → household.

## 6. The Change Enrolment transaction

`storeChange` is the only sanctioned way to end one enrolment and begin
another. The payload carries **both halves** — the closing side
(`current_end_date`, `current_closure_reason`) and the opening side
(`education_level_id`, `institution_name`, `start_date`, …) — and the controller
closes and opens them inside a single `DB::transaction`. Cross-field ordering is
validated in `ChangeEducationRequest`: the closing end date cannot precede the
current enrolment's start, and the new start cannot precede the closing end (a
child does not overlap two schools). The route requires both `education.close`
(on the existing row) and `education.create` (the opening half) — a
`education.create`-only operator cannot run a change, and the change form
itself 404s when there is no current enrolment. Two audit events are written:
`education_enrolment_closed` (with `via: 'change'`) and
`education_enrolment_created` (with `via: 'change'`).

## 7. Audit

Every state change is written through the existing `Auditor::record(...)`
architecture with a stable `SecurityEvent` case added for this phase:

- `education_enrolment_created` — a new enrolment opened.
- `education_enrolment_updated` — a CURRENT enrolment amended (never a closed
  one).
- `education_enrolment_closed` — an enrolment closed with an end date; the
  history row remains. Written by both the dedicated close flow and the Change
  flow (tagged `via: 'change'`).

Events carry a compact, non-sensitive `context` (beneficiary reference,
enrolment id, level code, start/end date) and never serialize objects or
credential material, per the Phase 3D audit contract.

## 8. Seeding

`EducationLevelSeeder` (wired into `DatabaseSeeder`) seeds the seven-level
vocabulary **idempotently** by construction: each row is keyed on `code` via
`updateOrCreate`, so re-seeding converges rather than duplicates, and a level an
administrator has deactivated (`is_active = false`) **stays deactivated**. The
`institutions` table is intentionally **not seeded** — schools are real-world
programme data, and seeding them would fabricate records; an enrolment always
carries the school by `institution_name` regardless.



## 9. Routes

All nine routes are nested under each beneficiary
(`beneficiaries/{beneficiary:beneficiary_ref}/education`, name prefix
`beneficiaries.`) because an enrolment **is** case content of the child it
describes. There is deliberately **no DELETE route**:

| Method | URI (relative) | Name | Permission |
| --- | --- | --- | --- |
| GET | `/education` | `beneficiaries.education.index` | `education.view` |
| GET | `/education/create` | `beneficiaries.education.create` | `education.create` |
| POST | `/education` | `beneficiaries.education.store` | `education.create` |
| GET | `/education/change` | `beneficiaries.education.change` | `education.create` |
| POST | `/education/change` | `beneficiaries.education.change.store` | `education.create` |
| GET | `/education/{enrolment}/edit` | `beneficiaries.education.edit` | `education.update` |
| PUT | `/education/{enrolment}` | `beneficiaries.education.update` | `education.update` |
| GET | `/education/{enrolment}/close` | `beneficiaries.education.close` | `education.close` |
| POST | `/education/{enrolment}/close` | `beneficiaries.education.close.confirm` | `education.close` |

## 10. Tests

Three focused feature suites under `tests/Feature/Education/`, sharing a
`BuildsEducationFixtures` concern:

- **`EducationWorkflowTest`** (12 tests) — the lifecycle: an enrolment opens
  current and a second is refused; the single-current rule is a model guard;
  closing dates the enrolment and keeps the row; change closes and opens in one
  transaction; an amendment cannot close or touch a closed enrolment; a closure
  before the start date is refused; invalid dates / invalid level / withdrawn
  level are refused; forged actor and beneficiary fields are derived
  server-side; an archived child cannot be updated or closed either; a change
  needs both close and create; change without a current enrolment is a 404; and
  the case-file page renders the education card.
- **`EducationAuthorizationTest`** (9 tests) — who may work with education
  history and through which door: a guest is redirected; a disabled operator is
  refused; permission without scope is denied; a view-only role sees history
  but cannot write it; a case officer may record and it is audited; a manager
  may view and close but not create; an archived child gets no new enrolment; an
  enrolment id under another child is a 404; and destructive deletion is
  unavailable (405).
- **`EducationScopeTest`** (4 tests) — programme AND geographic scope, each
  half proven independently: a programme-only operator cannot reach another
  state; a geography-only operator cannot reach another programme; both
  dimensions together grant and either alone refuses; and record-level isolation
  between two in-scope children.

The full project suite remains green (see the Phase 6A commit): the Phase 4/5
behaviour and the RBAC/scope/audit suites are unchanged, and the Phase 6A
additions pass on both the SQLite test driver and MySQL.

## 11. What this phase deliberately does not do

- **No institution (school) administrative surface.** The table exists and an
  enrolment may link to it, but there is no CRUD route yet; the recorded name
  remains the source of truth. A later phase can add school management without
  touching the enrolment history.
- **No deletion of education history.** The only terminal action is a dated,
  reasoned closure; `DELETE` returns 405.
- **No status enum.** "Current" is derived from `end_date IS NULL` (§2).
- **No health records / Phase 7.** Out of scope here.

