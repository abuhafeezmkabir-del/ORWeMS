# ORWeMS Phase 5 — Care, Family & Placement Foundation

> **Architectural rule:** Phases 3B and 3C answer *what* an operator may do
> and *where* that authority applies. Phase 4 built the first domain policy
> over a beneficiary. Phase 5 answers the question the case file exists for:
> **WHERE IS THIS CHILD LIVING, WITH WHOM, AND SINCE WHEN** — and **WHO IS
> FAMILY TO THEM**. Care history is evidence, so the terminal operation on a
> placement or a family link is a dated, reasoned **closure**, never a
> deletion. Nothing in this phase grants a capability or widens a scope; it
> can only record, amend-while-open, and close, and it refuses everything
> else through the same permission-then-scope chain as Phase 4.

## 1. What this phase is

Two workflows, both anchored to the beneficiary case file built in Phase 4:

1. **Care placements** — the append-only history of where a child has lived
   and who cared for them. A placement is opened, at most amended while it is
   the active one, and then **closed** by setting an end date. There is no
   delete route and no destroy method anywhere.
2. **Family / care relationships** — the first controlled workflow over the
   `person_relationships` model Phase 2.1 built but left without an interface.
   A link is recorded to one related party and later **ended**, never
   rewritten or removed.

Deliberately **not** in this phase: any household-centric (person-to-person)
relationship management, and any deletion path for care history. The model
supports person-to-person edges, but they have no route, workflow or policy
yet — `PersonRelationshipPolicy` refuses them rather than leaving them
reachable with a weaker check.

## 2. The single-active-placement rule, enforced twice

A child is in one place at a time. The rule that refuses a second *open*
placement is enforced at two layers on purpose, so neither has to be trusted
alone:

| Layer | Mechanism | Why it exists |
| --- | --- | --- |
| Model | `Placement::booted()` `saving` guard | Turns the violation into a **readable field error** ("use the Change flow"), covering every driver, and covers direct model writes (tinker, console) |
| Database | UNIQUE index on `(beneficiary_id, active_flag)` where `active_flag` is a **virtual generated column** = `1` while `end_date IS NULL`, `NULL` after | The **hard stop** that cannot be bypassed. UNIQUE ignores NULLs, so any number of *closed* rows coexist; only one row can hold flag `1`. This is what catches a race two operators slip past the model guard |

`status` is **derived, not chosen**: it mirrors `end_date`
(open → `active`, ended → `closed`). `PlacementStatus` has exactly two
states and no "draft" or "ended-but-unreviewed" — a record a caseworker must
remember to finish is a record that will be forgotten. A MySQL `CHECK`
constraint refuses status/date drift at the database level; the model guard
produces the same rule as a validation message on SQLite, which cannot add a
`CHECK` to an existing table.

`beneficiary_id`, `status` and `end_date` are **not mass-assignable**: the
beneficiary is set by the relation that creates the placement, and status /
end date are written only by the close and change flows, which also carry the
audit call.

## 3. The migration (one, additive)

`2026_10_08_100000_create_care_placement_tables` creates exactly two tables
and re-runs cleanly:

- **`care_arrangement_types`** — reference data (kinship, foster, residential,
  guardian, independent living, reunified). A seeded, flag-disabled table
  rather than an enum column, so a programme director can add an arrangement
  kind without a schema change, and existing placements keep their
  historical label even after a kind is withdrawn.
- **`placements`** — the history table above. `care_arrangement_type_id` is
  `restrictOnDelete` (a placement's kind cannot vanish under it);
  `household_id` and `caregiver_person_id` are `nullOnDelete` (removing a
  household removes the *link*, never the care history row).

MySQL-only `CHECK` constraints (`end_date >= start_date`; the status/end_date
pairing) are added as guarded raw statements via a Phase-2.1-style
`constraintExists()` helper, so re-applying migrations cannot fail on a
duplicate constraint. The generated-column single-active UNIQUE **is**
enforced on SQLite (the test driver) — the one rule that must never be
bypassable.

## 4. Placements workflow and lifecycle

| Ability | Permission | Policy condition |
| --- | --- | --- |
| `viewFor` | `placements.view` | permission **AND** beneficiary scope |
| `create` | `placements.create` | permission **AND** scope **AND** beneficiary not archived |
| `update` | `placements.update` | permission **AND** scope **AND** placement **is active** |
| `close` | `placements.close` | permission **AND** scope **AND** placement **is active** |

The scope checked is always the scope of the placement's **beneficiary** — a
placement is case content of the child it describes and it follows the child.
`update` and `close` refuse an already-closed placement: amending or
re-closing history would silently rewrite when and where a child was cared
for.

**Change is the one compound action.** Closing the current placement and
opening the next happen in **one transaction** (`storeChange`), because a
child is somewhere at every moment; two separate requests would leave a
window — literal if seconds apart, logical if the second fails — where a
child has *no* active placement. Change therefore requires **both**
`placements.close` and `placements.create`; the controller authorizes
`close` on the existing row and `create` on the beneficiary, so a manager who
holds close-but-not-create cannot run it. The change payload carries both
halves, and validation refuses an opening `start_date` earlier than the
closing `current_end_date`.

**Input-level scope.** `VerifiesCareScope` (shared by the store, update and
change requests) re-checks that a named household *and* a named caregiver sit
in areas the operator is scoped to — deny-by-default, so a household or
person with no recorded community is refused with an explanation, not waved
through. The form lookups only ever offer in-scope households and persons, but
the submit-time check stays regardless: **a form is not a control**.

## 5. Family / care relationships workflow

`PersonRelationshipPolicy` is beneficiary-anchored: `view` and `create` take
the beneficiary; `update` — used only for **ending** a link, never rewriting
what a relationship *was* — resolves the beneficiary through the edge's
related party and refuses person-only edges (which have no workflow yet) and
already-ended links.

`StoreRelationshipRequest` states the exactly-one-related-party rule (the
`person_relationships` CHECK from Phase 2.1) as validation, and supports three
counterpart modes:

- **`existing_person`** — must live in an in-scope household and must not be
  the child's own roster row (a child is not related to themselves).
- **`new_person`** — creates the roster person in the same transaction, and
  additionally requires **`persons.create`** (checked in `authorize()`), so
  recording a relationship cannot become a back door to growing household
  rosters without the roster permission. A missing capability is a **403**,
  not a validation error — it is not about the input.
- **`existing_beneficiary`** — the counterpart child must be a different,
  non-archived, in-scope beneficiary (typically a registered sibling); its
  roster row is created from the child's own identity if absent.

Duplicate **open** edges are refused: the same kind of relationship to the
same person, not yet ended, already exists and should be amended or ended
rather than shadowed by a second row.


## 6. Authorization architecture (unchanged from Phase 3–4, reused in full)

Every care action runs the same chain, link by link, and every test asserts
it:

```
route middleware (auth → account.active → password.fresh → permission:*)
   → policy (permission AND beneficiary scope, plus lifecycle condition)
      → form request (input-level scope for household / caregiver / counterpart)
         → model guards + database constraints
```

- **Guest** → redirected to login (`auth`).
- **Disabled account** → refused (`account.active`).
- **Permission without scope** → 403 (policy). Verified by a case officer
  scoped to Nasarawa against a child in Niger.
- **Cross-record id in the URL** → 404, not 403, because a 403 would confirm
  the id exists (`assertPlacementBelongsTo`).
- **Archived child** → no new placement or relationship (policy refuses).
- **Separation of duties**: a view-only role (`registration_officer`) sees
  placement history but cannot record; a `programme_manager` holds
  `placements.view` + `placements.close` but not `create`; only a
  `case_officer` runs the whole loop.

The `Gate::before` hook still answers only *permission-code* abilities and
returns `null` for class-name abilities, so `PlacementPolicy` and
`PersonRelationshipPolicy` fall through untouched — the binding only says
*which* policy answers, never *what* it answers.

## 7. Audit trail

Five new events extend the stable `SecurityEvent` vocabulary (append-only
`audit_logs`, single writer):

| Event | Written when |
| --- | --- |
| `care_placement_created` | a placement is opened |
| `care_placement_updated` | an **active** placement is amended (never a closed one) |
| `care_placement_closed` | a placement is closed with an end date; the row remains |
| `beneficiary_relationship_created` | a family/care link is recorded |
| `beneficiary_relationship_updated` | a link is ended by setting its end date |

Each carries the beneficiary reference and the record id in `context`; no
objects are serialised and no credential material is written, per the Phase 3D
audit contract.

## 8. Frontend and case-file integration

The beneficiary case file (`beneficiaries/show`) renders **Care History** and
**Family / relationships** cards, each gated by its own `@can` so an operator
only sees the actions they hold. Dedicated Blade views under
`resources/views/placements/` (index, create, edit, change, close) and
`resources/views/beneficiaries/relationships/` (create, end) present the
forms and histories. `npm run build` compiles cleanly (only a pre-existing
Sass `@import` deprecation notice, unrelated to this phase).



## 9. Test coverage

`tests/Feature/Care/` — **35 tests, 122 assertions**, all passing:

- `PlacementAuthorizationTest` — the full auth chain (guest, disabled,
  permission-without-scope, view-only, manager, archived child, cross-record
  404, destructive-deletion unavailable).
- `PlacementWorkflowTest` — single-active rule (model message and database
  backstop), closure keeps the row, the change transaction, bounded closure
  dates, out-of-scope household refused at submit, a withdrawn arrangement
  kind refused, change requires both capabilities, the case-file page renders
  the care cards.
- `RelationshipWorkflowTest` — recording and audit, view-only refusal, the
  `persons.create` back-door check, scope refusals, and ending as a dated
  close (not a deletion) that cannot predate the link or be applied twice.

The full project suite passes: **347 passed, 6 skipped (1361 assertions)**.
The six skips are Phase 2 database-level CHECK tests that only run on MySQL,
marked skipped on the SQLite test driver — unchanged by this phase.

## 10. Reference

- Migration: `database/migrations/2026_10_08_100000_create_care_placement_tables.php`
- Models: `App\Models\Placement`, `App\Models\CareArrangementType`, enum `App\Enums\PlacementStatus`
- Controllers: `App\Http\Controllers\PlacementController`, `App\Http\Controllers\BeneficiaryRelationshipController`
- Policies: `App\Policies\PlacementPolicy`, `App\Policies\PersonRelationshipPolicy` (registered in `AppServiceProvider`)
- Requests: `StorePlacementRequest`, `UpdatePlacementRequest`, `ChangePlacementRequest`, `ClosePlacementRequest`, `StoreRelationshipRequest`, `EndRelationshipRequest`, trait `Concerns\VerifiesCareScope`
- Seeders: `CareArrangementTypeSeeder` (idempotent, `updateOrCreate` by `code`)
- Permissions: `placements.view|create|update|close`, `relationships.view|create|update` (roles wired in `RolePermissionSeeder`)
