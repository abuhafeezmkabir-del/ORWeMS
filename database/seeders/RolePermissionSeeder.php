<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * Phase 3B — which role receives which permission.
 *
 * This is the least-privilege decision, written out in one reviewable place.
 * Every absence below is as deliberate as every presence: a permission omitted
 * from a role is refused, because the system denies by default.
 *
 * The recurring rule is SEPARATION OF DUTIES — the person who enters a record
 * is not the person who confirms it, and the person who does the casework is
 * not the person who administers the system. Three splits drive the mapping:
 *
 *   entry (registration_officer) -> verification (programme_manager)
 *   assessment (assessor)        -> approval (programme_manager)
 *   casework (case_officer)      -> administration (system_administrator)
 *
 * Idempotent by construction: `sync` converges on exactly the set below, so
 * running the seeder repeatedly neither duplicates grants nor accumulates drift.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::mapping() as $roleCode => $permissionCodes) {
            $role = Role::where('code', $roleCode)->first();

            // Silent here would mean a role silently received nothing.
            if ($role === null) {
                throw new \RuntimeException(
                    "Role [{$roleCode}] is not defined. Run RoleSeeder before RolePermissionSeeder."
                );
            }

            $role->permissions()->sync(
                Permission::query()
                    ->whereIn('code', $permissionCodes)
                    ->pluck('id')
                    ->all()
            );
        }

        PermissionCatalog::flush();
    }

    /**
     * Role code => the permission codes it confers.
     *
     * @return array<string, list<string>>
     */
    public static function mapping(): array
    {
        return [
            // Break-glass ownership. Every permission is granted through this
            // ordinary pivot row so the grant is visible in the administration
            // screens and auditable in the database like any other.
            //
            // This is a ROLE, not a bypass: it obeys account status (a disabled
            // super administrator is locked out), it appears in role-permission
            // listings, and revoking its permissions removes its power exactly
            // as it would for any other role.
            'super_administrator' => array_column(
                PermissionSeeder::permissions(),
                'code'
            ),

            // Administration only. Note what is ABSENT: no beneficiaries.*, no
            // households.*, no documents.*. WICHOV's administrator manages the
            // platform; reading children's case records is a different job with
            // a different risk profile, and merging them would hand every
            // administrator full read access to beneficiary data.
            'system_administrator' => [
                'users.view', 'users.create', 'users.update', 'users.disable',
                'users.assign_roles', 'roles.view', 'roles.manage',
                'programmes.view', 'programmes.manage',
                'system.settings', 'system.maintenance',
            ],

            // Oversight: confirms and closes, but does not do the data entry.
            // The absence of beneficiaries.create and assessments.create is the
            // point — a manager signs off work, they do not enter it.
            'programme_manager' => [
                'beneficiaries.view', 'beneficiaries.verify', 'beneficiaries.archive',
                'households.view', 'households.archive',
                'persons.view', 'relationships.view',
                // Care oversight without casework entry, mirroring the
                // needs/interventions split: the manager SEES the placement
                // history and closes arrangements (the separation-of-duties
                // action on a placement), but does not open or edit them.
                'placements.view', 'placements.close',
                // Education history follows the same oversight shape: the
                // manager SEES it and closes an enrolment, but does not open
                // or amend one — that is casework.
                'education.view', 'education.close',
                // Health history follows the same oversight shape: the manager
                // SEES it and closes a record, but does not open or amend one —
                // that is casework. Health data is the most sensitive content
                // ORWeMS holds, so the manager sees only the records of
                // children already in their programme/geographic scope —
                // scope narrows every record, as for every other family.
                'health_records.view', 'health_records.close',
                'assessments.view', 'assessments.approve',
                'needs.view', 'needs.close',
                'interventions.view', 'interventions.close',
                'documents.view',
                'programmes.view', 'reports.view', 'reports.export',
                'users.view',
            ],

            // Field casework end to end. Note the ABSENCES that matter here:
            // no beneficiaries.verify (they did not enter the record, so they do
            // not confirm it), no reports.export (a caseworker exports data about
            // named children), and no administration of any kind.
            'case_officer' => [
                'beneficiaries.view', 'beneficiaries.create', 'beneficiaries.update',
                'households.view', 'households.create', 'households.update',
                'persons.view', 'persons.create', 'persons.update',
                'relationships.view', 'relationships.create', 'relationships.update',
                // End-to-end placement casework: record, amend while active,
                // close. The close/update split still binds inside the role —
                // the POLICY refuses amending a closed placement, so "edit
                // the history" is not a power this role (or any role) holds.
                'placements.view', 'placements.create', 'placements.update', 'placements.close',
                // End-to-end education casework: record, amend while current,
                // close. The close/update split still binds inside the role —
                // the POLICY refuses amending a closed enrolment, so "edit
                // the history" is not a power this role (or any role) holds.
                'education.view', 'education.create', 'education.update', 'education.close',
                // End-to-end health casework: record, amend while open, close.
                // The close/update split still binds inside the role — the
                // POLICY refuses amending a closed record, so "edit the
                // history" is not a power this role (or any role) holds.
                'health_records.view', 'health_records.create', 'health_records.update', 'health_records.close',
                'assessments.view', 'assessments.create', 'assessments.update',
                'needs.view', 'needs.create', 'needs.update',
                'interventions.view', 'interventions.create', 'interventions.update',
                'documents.view', 'documents.upload',
                'programmes.view',
            ],

            // Narrow intake. Data entry without the power to confirm: the
            // registration can be entered accurately and completely, but
            // confirming it is a different person's decision.
            'registration_officer' => [
                'beneficiaries.view', 'beneficiaries.create', 'beneficiaries.update',
                'households.view', 'households.create', 'households.update',
                'persons.view', 'persons.create', 'persons.update',
                'relationships.view', 'relationships.create',
                // Placement HISTORY is visible at intake (knowing where a
                // child currently lives matters to every conversation), but
                // recording, editing and closing placements are casework —
                // deliberately absent here. Education history is visible for
                // the same reason (a child's schooling is core intake
                // context); writing it is casework.
                'placements.view',
                'education.view',
                // Health is deliberately ABSENT at intake. Placement and
                // education history are visible because they shape the intake
                // conversation; health history is disclosed only to the
                // caseworkers who need it, under their own permissions.
                // Least privilege means the default is no health access.
                'documents.view', 'documents.upload',
                'programmes.view',
            ],

            // Assessment, separated from registration: someone can assess a
            // household's circumstances without also being the person who typed
            // its members in. No assessments.approve — signing off is the
            // programme manager's, so the rating is reviewed above the assessor.
            'assessor' => [
                'beneficiaries.view',
                'households.view',
                'persons.view',
                // Assessment is done WITH the care context in view — a
                // child's current arrangement is standard assessment input.
                'placements.view',
                // Education history is standard assessment input too (school
                // attendance and level shape a welfare picture); assessing is
                // not recording it. Health, by contrast, is not assessment
                // input by default: an assessor sees health records only when
                // WICHOV explicitly grants the health permissions, never
                // through the assessment role itself.
                'education.view',
                'assessments.view', 'assessments.create', 'assessments.update',
                'needs.view', 'needs.create', 'needs.update',
                'interventions.view',
                'documents.view', 'documents.upload',
                'programmes.view',
            ],

            // Read-only oversight. Every `.view` in the system, and deliberately
            // nothing else:
            //   - no create/update/verify/approve/close, so oversight cannot be
            //     quietly converted into action;
            //   - NO reports.export, because an auditor's value is in being able
            //     to read what happened, not in being able to remove it in bulk
            //     — export is the classic route for bulk exfiltration.
            'auditor' => array_values(array_filter(
                array_column(PermissionSeeder::permissions(), 'code'),
                static fn (string $code): bool => str_ends_with($code, '.view')
            )),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Roles evaluated and deliberately NOT created
    |--------------------------------------------------------------------------
    |
    | Recorded so the catalogue can be reviewed rather than merely trusted.
    |
    | education_officer / health_officer
    |     ORWeMS will need sector officers, but there is still no health module:
    |     no model, no controller, no route. Creating a health_officer role now
    |     would mean inventing permissions (`health.case_notes.view`) for screens
    |     that cannot exist, and would leave a plausible-looking role that grants
    |     nothing and appears to be broken. The permission model supports it
    |     without change when the module is built — and because roles carry no
    |     location, one `health_officer` will serve every state.
    |
    |     As of Phase 6A the EDUCATION module exists, so the reason no longer
    |     applies to education. An `education_officer` role was still not seeded,
    |     deliberately: the four `education.*` permissions are mapped onto the
    |     existing casework roles (case_officer holds them end to end;
    |     programme_manager oversees and closes; intake/assessor view). Adding a
    |     separate education_officer now would duplicate case_officer's education
    |     slice under a second name, and duplicate roles are how privilege drift
    |     starts. WICHOV can split a dedicated sector officer out later, once it
    |     is a real post, using the same permission codes.
    |
    | research_analyst
    |     ORWeMS is also a PhD research platform, and a research role is
    |     foreseeable. It is withheld deliberately. Research access must not be
    |     blanket access to raw beneficiary records, and the controls that make
    |     research safe — de-identification and data scope — are Phase 3C and
    |     later. Creating the role now would grant it real beneficiary access
    |     before the safeguards that justify that access exist.
    |
    | read_only_viewer
    |     Covered by `auditor`, which already holds every `.view` permission and
    |     nothing else. A second read-only role would be a duplicate with a
    |     different name, and duplicate roles are how privilege drift starts:
    |     one gets reviewed, the other is forgotten.
    |
    | welfare_manager
    |     Overlaps `programme_manager` exactly for the pilot. Splitting
    |     supervision from programme ownership would be reasonable once both
    |     functions exist as distinct WICHOV posts; until then it is one job
    |     wearing two names, and the separation is documented here for when that
    |     changes rather than seeded as an empty shell.
    */
}