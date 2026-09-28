# ORWeMS — Phase 2 Database Architecture

**Project:** ORWeMS (Orphans Registration and Welfare Management System)
**Client:** WICHOV — Welfare Initiative for Children, Orphans and Vulnerable
**Phase:** 2 — Database Foundation
**Baseline:** Phase 0 / 1A / 1B (landing page, layout, dashboard shell)

This document describes the relational foundation created in Phase 2. It is a
practical reference for the next phases, not an academic specification.

---

## 1. Scope of Phase 2

Established:

* Geographic reference data (country → state → LGA → ward → community).
* Minimal programme foundation (no multi-tenancy).
* Beneficiary core identity record with a stable internal reference.
* Household / person / relationship foundation (no `father_name` columns).
* Assessment → Need foundation (longitudinal).
* Minimum intervention foundation (need-linked, no allocation logic).
* Document metadata foundation (no file uploads, no BLOBs).
* Reference/lookup data for sex, statuses, relationship types, need
  categories and intervention types.

Intentionally **not** built in Phase 2: authentication/RBAC, beneficiary CRUD
screens, education/health/care modules, needs-assessment UI, intervention
management, follow-up/outcomes, audit-log engine, IWAE scoring, reporting,
research module (see §11).

---

## 2. Tables Created

| # | Table | Purpose |
|---|-------|---------|
| 1 | `countries` | Top-level geography (ISO-3166 alpha-3 code, nationality). |
| 2 | `states` | Second-level division. `type_label` allows "Province"/"Region"/"Territory" without schema change. |
| 3 | `local_government_areas` | Third-level division (LGA / district / equivalent). `type_label` default `LGA`. |
| 4 | `wards` | Fourth-level division. `type_label` default `Ward`. |
| 5 | `communities` | Fifth-level settlement/village record, optionally linked to a ward. |
| 6 | `programmes` | Minimal programme identity so programme names/codes are data, not hard-coded. |
| 7 | `relationship_types` | Reference vocabulary of household/family relationship types. |
| 8 | `households` | A household/family unit with a unique `household_code`. Soft-deleted. |
| 9 | `persons` | Natural persons in a household (guardians, caregivers, siblings, adults). Soft-deleted. |
| 10 | `beneficiaries` | The child beneficiary core identity record. Soft-deleted. |
| 11 | `person_relationships` | Relationship edges between persons and/or a beneficiary (history-capable). |
| 12 | `need_categories` | Reference vocabulary of need domains. |
| 13 | `assessments` | Dated assessments belonging to a beneficiary (many per child). |
| 14 | `needs` | Needs identified by a specific assessment. |
| 15 | `intervention_types` | Reference vocabulary of intervention types. |
| 16 | `interventions` | Interventions delivered to a beneficiary, optionally linked to a need. |
| 17 | `documents` | Document **metadata only** for future secure document management. Soft-deleted. |

Framework tables from Phase 1 remain unchanged: `users`,
`password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
`job_batches`, `failed_jobs`, `migrations`.

---

## 3. Important Columns

### Geographic reference data
* `countries`: `name`, `iso_code` (unique), `nationality`, `is_active`.
* `states`: `country_id` (FK, restrict), `name`, `code`, `type_label`, `is_active`. Unique `(country_id, name)`.
* `local_government_areas`: `state_id` (FK, restrict), `name`, `code`, `type_label`, `is_active`. Unique `(state_id, name)`.
* `wards`: `local_government_area_id` (FK, restrict), `name`, `code`, `type_label`, `is_active`. Unique `(local_government_area_id, name)`.
* `communities`: `ward_id` (FK, nullable, null-on-delete), `local_government_area_id` (FK, restrict), `name`, `code`, `type_label`, `is_active`. Unique `(local_government_area_id, name)`.

### Programme
* `programmes`: `name`, `code` (unique), `description`, `status`.

### Beneficiary
* `beneficiary_ref` — unique, stable, e.g. `WICHOV-000001`.
* Biodata: `first_name`, `middle_name` (nullable), `last_name`, `sex`, `date_of_birth`, `date_of_birth_accuracy`.
* Geography: `nationality_country_id`, `country_id`, `state_id`, `local_government_area_id`, `ward_id`, `community_id`.
* Household: `household_id`, `person_id` (optional link to this child's own `persons` row).
* Programme: `programme_id` (nullable).
* Lifecycle: `registration_date` (required), `status`, `notes`, `timestamps`, `deleted_at`.

### Persons / relationships
* `persons`: `household_id`, name parts, `sex`, `date_of_birth`, `phone`, `is_guardian`, `is_caregiver`, `is_alive`, `notes`.
* `person_relationships`: `relationship_type_id`, `person_id`, `related_person_id` (nullable), `related_beneficiary_id` (nullable), `start_date`, `end_date`, `is_primary_guardian`, `notes`.

### Assessment / need / intervention
* `assessments`: `beneficiary_id`, `programme_id` (nullable), `assessment_date`, `assessment_type`, `assessor_name`, `summary`.
* `needs`: `assessment_id`, `beneficiary_id`, `need_category_id`, `title`, `description`, `priority` (nullable ordinal), `status`.
* `interventions`: `beneficiary_id`, `need_id` (nullable), `intervention_type_id`, `programme_id` (nullable), `start_date`, `end_date`, `status`, `notes`.

### Documents
* `documents`: `documentable_type` / `documentable_id` (morph), `document_type`, `original_filename`, `storage_path`, `mime_type`, `file_size`, `uploaded_by`, `timestamps`, `deleted_at`.

---

## 4. Relationship Map

```text
Country
  ↓ hasMany
State            (type_label: State | Province | Region | Territory)
  ↓ hasMany
LocalGovernmentArea  (type_label: LGA | District | Area Council ...)
  ↓ hasMany
Ward
  ↓ hasMany
Community

Household
  ├─ hasMany  Person                 (guardians, caregivers, siblings, adults)
  ├─ hasMany  Beneficiary
  └─ PersonRelationship
         ├─ belongsTo RelationshipType
         ├─ belongsTo Person              (subject)
         ├─ belongsTo related Person      (nullable)
         └─ belongsTo related Beneficiary (nullable)

Beneficiary
  ├─ belongsTo Household, Country, State, LGA, Ward, Community
  ├─ belongsTo Person        (this child's own household-roster row, optional)
  ├─ belongsTo Programme
  ├─ hasMany   Assessment
  ├─ hasMany   Need
  └─ hasMany   Intervention

Assessment
  └─ hasMany   Need

Need
  ├─ belongsTo Assessment, Beneficiary, NeedCategory
  └─ hasMany   Intervention        (future: Follow-up → Outcome)

Intervention
  ├─ belongsTo Beneficiary, Need (nullable), InterventionType, Programme
  └─ future: Follow-up / Outcome child tables

Document (metadata only)
  └─ morphTo Beneficiary | Household | Person | Assessment | ... (future)
```

---

## 5. Primary Keys

Every Phase 2 table uses Laravel's conventional auto-incrementing
`unsignedBigInteger` primary key named `id`. No personal name, national ID
number or government identifier is ever used as a primary key.

## 6. Foreign Keys and Deletion Behaviour

| Child | Column | Parent | On delete |
|---|---|---|---|
| states | country_id | countries | RESTRICT |
| local_government_areas | state_id | states | RESTRICT |
| wards | local_government_area_id | local_government_areas | RESTRICT |
| communities | local_government_area_id | local_government_areas | RESTRICT |
| communities | ward_id | wards | SET NULL |
| beneficiaries | household_id | households | SET NULL |
| beneficiaries | person_id | persons | SET NULL |
| beneficiaries | country/state/lga/ward/community/nationality | *_id | SET NULL |
| beneficiaries | programme_id | programmes | SET NULL |
| persons | household_id | households | SET NULL |
| person_relationships | relationship_type_id | relationship_types | RESTRICT |
| person_relationships | person_id | persons | RESTRICT |
| person_relationships | related_person_id | persons | SET NULL |
| person_relationships | related_beneficiary_id | beneficiaries | SET NULL |
| assessments | beneficiary_id | beneficiaries | RESTRICT |
| assessments | programme_id | programmes | SET NULL |
| needs | assessment_id | assessments | RESTRICT |
| needs | beneficiary_id | beneficiaries | RESTRICT |
| needs | need_category_id | need_categories | RESTRICT |
| interventions | beneficiary_id | beneficiaries | RESTRICT |
| interventions | need_id | needs | RESTRICT |
| interventions | intervention_type_id | intervention_types | RESTRICT |
| interventions | programme_id | programmes | SET NULL |
| documents | uploaded_by | users | SET NULL |

Rationale:

* **RESTRICT** protects historical humanitarian records. A beneficiary,
  assessment or need with dependent records cannot be deleted without an
  explicit, reviewed decision.
* **SET NULL** is used only for optional context (programme, geography,
  optional person link) where losing the link does not destroy history.
* **`cascadeOnDelete()` is deliberately not used anywhere in Phase 2.**
* Soft deletes handle normal "removal" of `beneficiaries`, `households`,
  `persons` and `documents`, keeping the row — and therefore the audit
  trail — intact.

## 7. Important Indexes and Uniqueness

Uniqueness:

* `countries.iso_code`
* `states (country_id, name)`
* `local_government_areas (state_id, name)`
* `wards (local_government_area_id, name)`
* `communities (local_government_area_id, name)`
* `programmes.code`
* `relationship_types.code`, `need_categories.code`, `intervention_types.code`
* `households.household_code`
* `beneficiaries.beneficiary_ref`

Indexes (lookup / filter / research orientation):

* geography: `states (country_id, is_active)`, `wards (local_government_area_id, is_active)`, `communities (ward_id, is_active)`
* beneficiaries: `status`, `registration_date`, `(state_id, local_government_area_id)`, `(last_name, first_name)`
* persons: `(last_name, first_name)`
* assessments: `(beneficiary_id, assessment_date)`, `assessment_date`
* needs: `(assessment_id, need_category_id)`, `(beneficiary_id, status)`
* interventions: `(beneficiary_id, status)`, `(need_id, status)`, `start_date`
* person_relationships: `(person_id, relationship_type_id)`
* documents: `(documentable_type, documentable_id)`, `document_type`
* programmes: `status`

Single-column foreign keys (`household_id`, `related_person_id`, etc.) are
indexed by InnoDB automatically as part of the constraint, so no duplicate
index is declared for them. No index is placed on columns that are never
filtered or joined.

## 8. Reference Data Seeded

Seeded by `DatabaseSeeder` (all seeders idempotent via `updateOrCreate` /
`firstOrCreate`):

* **Countries:** Nigeria (ISO `NGA`, nationality `Nigerian`).
* **States — verified pilot scope only:** Niger State (`NG-NI`),
  Nasarawa State (`NG-NA`), Federal Capital Territory (`NG-FC`).
* **Programmes:** `WICHOV-PILOT` — "WICHOV Pilot Programme" (status `active`).
* **Relationship types (12):** mother, father, guardian, caregiver, sibling,
  grandmother, grandfather, aunt, uncle, relative, household head, other.
* **Need categories (10):** education, health, nutrition, shelter, wash,
  protection, psychosocial, livelihood, legal documentation, other.
* **Intervention types (11):** education, health, nutrition, shelter, WASH,
  psychosocial, protection, livelihood, legal, cash transfer, other support.

**Deliberately not seeded:** LGAs, wards and communities — these must come
from verified project geography data. No beneficiaries, households, persons,
relationships, assessments, needs or interventions are fabricated.

Local development only: one non-identifying convenience user
(`test@example.com`) is created when the environment is `local` or `testing`.
It is never created in production.

The 12 relationship types, 10 need categories and 11 intervention types are
generic starting vocabularies (reference data), not invented geography. They
are stored as data and are safe for the programme team to rename or extend.

## 9. Deletion and Soft-Delete Decisions

| Table | Soft deletes | Reason |
|---|---|---|
| `beneficiaries` | Yes | A child's record must never silently vanish; exits are recorded via `status`, and mistaken records can be withdrawn while remaining auditable. |
| `households` | Yes | Households are closed/merged rather than destroyed; members and history stay intact. |
| `persons` | Yes | Guardians/caregivers may be replaced or corrected without losing prior relationships. |
| `documents` | Yes | Withdrawing a document must leave an auditable trace of its former existence and storage reference. |
| `assessments`, `needs`, `interventions` | No | Historical capacity records. They are protected by `RESTRICT` foreign keys instead; corrections are made by adding new dated records, not deleting old ones. |
| `person_relationships` | No | Relationship history (with `start_date`/`end_date`) is preserved; a relationship ends by setting `end_date`. |
| Geography, reference and programme tables | No | Reference data is corrected by editing rows; `RESTRICT` prevents removing a geography row that beneficiaries depend on. |

## 10. Historical / Longitudinal and Research Considerations

* Every assessment is a separate dated row: baseline, review and follow-up
  assessments accumulate instead of overwriting a "current" value.
* Needs are always attached to the assessment that identified them, so
  "what was known at assessment X" can be reconstructed exactly.
* Interventions optionally point back to the need they address, so the chain
  `Assessment → Needs → Interventions` is reconstructable for any date range.
* Relationship rows carry `start_date` / `end_date`, so household composition
  over time is representable.
* Structured research variables live in typed relational columns
  (`sex` enum, `date_of_birth`, `date_of_birth_accuracy`, category FKs,
  `status` enums, dates). JSON is not used for research-relevant data.
* Stable surrogate keys plus the stable `beneficiary_ref` allow longitudinal
  linkage and reproducible analysis without expiring identifiers.
* `created_at` / `updated_at` are present on every business table so record
  provenance is available for the later audit phase.

## 11. Future Module Compatibility

| Future module | How it attaches without redesigning `beneficiaries` |
|---|---|
| Education records | New `education_records` table referencing `beneficiary_id` (+ dates, institution). |
| Health records | New `health_records` table referencing `beneficiary_id`. |
| Care / placement | New `placements` / `care_records` table referencing `beneficiary_id` and `household_id`. |
| Follow-up and outcomes | New `intervention_follow_ups`, `intervention_outcomes` referencing `intervention_id`. |
| Prioritisation (IWAE) | Operates on `assessments` → `needs` (`priority`, `status`) → `interventions`; no schema change required. |
| Authentication / RBAC | `users`, roles/permissions plus `created_by`/`verified_by` FK columns added to existing tables. |
| Audit logging | Additive `audit_logs` (or activity log) table; Phase 2 already preserves `created_at`/`updated_at`/`deleted_at`. |
| Documents | `documents` morph table already in place for secure, private storage. |
| Reporting / research | Existing indexes and date columns support cohort and longitudinal queries. |

## 12. Decisions Intentionally Deferred

* Authentication, users/RBAC fields on business tables (Phase 3).
* Beneficiary, household, assessment and intervention management screens.
* Education, health and care/placement modules.
* Follow-up and outcome capture; outcome measurement.
* IWAE priority scoring, weights and allocation rules (`needs.priority`
  exists only as a nullable ordinal placeholder).
* Full audit-log/activity-log engine and field-level change history.
* Secure file upload, virus scanning and document access control.
* Verified LGA/ward/community reference import for the pilot states.
* Beneficiary reference format is configurable
  (`config/orwems.php` → `beneficiary_ref_prefix`, `beneficiary_ref_padding`);
  any future numbering-scheme change can be introduced there.
* Additional `sex` / status enumeration values (e.g. `unknown`, `not_disclosed`)
  if programme field practice requires them — all enumeration columns are
  plain strings, so adding values needs no schema change.




