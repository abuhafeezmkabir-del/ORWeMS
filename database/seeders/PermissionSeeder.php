<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * Phase 3B — the ORWeMS permission vocabulary.
 *
 * REFERENCE DATA ONLY. This defines what capabilities EXIST; RolePermissionSeeder
 * decides who receives them. Keeping the two apart is what makes the vocabulary
 * safe to extend: adding a code here grants it to nobody until a role is given
 * it, so a new capability can never leak into production merely by being
 * invented.
 *
 * Naming convention — `<resource>.<action>`, lowercase snake_case:
 *
 *     beneficiaries.view      beneficiaries.create   beneficiaries.verify
 *
 * The CODE is the only authorization key. `name` is a display label that may be
 * reworded freely without altering what anyone may do.
 *
 * CRUD alone is deliberately NOT the vocabulary. Humanitarian casework has
 * actions that are not "update", and flattening them would let anyone who can
 * edit a record also VERIFY it or CLOSE a case:
 *
 *   verify   a registration is confirmed against evidence by someone other than
 *            the person who entered it — separation of duties;
 *   approve  an assessment is signed off;
 *   close    a need or intervention reaches a terminal state;
 *   archive  a record leaves active view but is retained.
 *
 * There are NO delete permissions, anywhere. ORWeMS holds historical records of
 * children; permanent destruction is not introduced as an operation merely to
 * give RBAC something to restrict. Records are archived, and that is Phase 3D's
 * concern, not Phase 3B's.
 *
 * Only families that map to something ORWeMS actually has are defined. Families
 * whose permissions would be pure invention — health — are absent until that
 * module exists; the care/placement family was added in Phase 5 alongside the
 * module that gave it meaning, and the education family in Phase 6A. The
 * catalogue is a plain table, so adding `health.*` when the health module is
 * built is additive and needs no migration.
 *
 * The catalogue is defined as DATA rather than as code so that an administrator
 * can extend it, and so a test can assert the exact intended size instead of
 * trusting a hand-maintained list to stay in step with the database.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::permissions() as $permission) {
            $existing = Permission::where('code', $permission['code'])->first();

            Permission::updateOrCreate(
                ['code' => $permission['code']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    // An existing row keeps its current state: re-running the
                    // seeder must never silently re-activate a permission that
                    // was deliberately withdrawn.
                    'is_active' => $existing?->is_active ?? true,
                ]
            );
        }

        PermissionCatalog::flush();
    }

    /**
     * The complete Phase 3B vocabulary.
     *
     * Grouped by resource so a reviewer can see the shape of the system at a
     * glance: every entry is either a plain action on a record or a distinct
     * domain step (verify, approve, close, archive) that separation of duties
     * requires to be separable.
     *
     * @return list<array{code: string, name: string, description: string}>
     */
    public static function permissions(): array
    {
        return [
            // --- Beneficiaries ------------------------------------------------
            // Registration is the entry point of the whole platform, so it is
            // the first family defined.
            ['code' => 'beneficiaries.view', 'name' => 'View beneficiaries', 'description' => 'See beneficiary records and their identifying details.'],
            ['code' => 'beneficiaries.create', 'name' => 'Register beneficiaries', 'description' => 'Create new beneficiary registration records.'],
            ['code' => 'beneficiaries.update', 'name' => 'Edit beneficiary records', 'description' => 'Amend details on an existing beneficiary record.'],
            ['code' => 'beneficiaries.verify', 'name' => 'Verify beneficiaries', 'description' => 'Confirm a registration against documentary evidence, separately from entering it.'],
            ['code' => 'beneficiaries.archive', 'name' => 'Archive beneficiaries', 'description' => 'Withdraw a beneficiary record from active view while retaining it.'],

            // --- Households ----------------------------------------------------
            ['code' => 'households.view', 'name' => 'View households', 'description' => 'See household records and their composition.'],
            ['code' => 'households.create', 'name' => 'Create households', 'description' => 'Register new households.'],
            ['code' => 'households.update', 'name' => 'Edit households', 'description' => 'Amend household details.'],
            ['code' => 'households.archive', 'name' => 'Archive households', 'description' => 'Withdraw a household from active view while retaining it.'],

            // --- Persons and relationships -------------------------------------
            // Persons cover the whole household roster, not only beneficiaries,
            // which is why this family is separate from beneficiaries.
            ['code' => 'persons.view', 'name' => 'View persons', 'description' => 'See records of individuals on a household roster.'],
            ['code' => 'persons.create', 'name' => 'Create persons', 'description' => 'Add individuals to a household roster.'],
            ['code' => 'persons.update', 'name' => 'Edit persons', 'description' => 'Amend household roster records.'],

            ['code' => 'relationships.view', 'name' => 'View relationships', 'description' => 'See how people within and between households are related.'],
            ['code' => 'relationships.create', 'name' => 'Record relationships', 'description' => 'Add relationship links within and between households.'],
            ['code' => 'relationships.update', 'name' => 'Edit relationships', 'description' => 'Amend recorded relationships.'],

            // --- Care placements (Phase 5) --------------------------------------
            // The care-history family. `close` exists for the same reason it
            // does for needs and interventions: ending a placement is a
            // distinct domain decision, separable from editing one, so the
            // person who runs the case is not automatically the person who
            // decides when it stopped.
            ['code' => 'placements.view', 'name' => 'View care placements', 'description' => 'See a child’s current care arrangement and placement history.'],
            ['code' => 'placements.create', 'name' => 'Record care placements', 'description' => 'Open a new care placement record for a child.'],
            ['code' => 'placements.update', 'name' => 'Edit care placements', 'description' => 'Amend the substance of an active care placement.'],
            ['code' => 'placements.close', 'name' => 'Close care placements', 'description' => 'End an active care placement with a dated closure.'],

            // --- Education (Phase 6A) -------------------------------------------
            // The education-history family. `close` exists for the same reason it
            // does for placements, needs and interventions: ending an enrolment
            // is a distinct domain decision, separable from editing one, so the
            // person who runs the case is not automatically the person who
            // decides when it stopped.
            ['code' => 'education.view', 'name' => 'View education history', 'description' => 'See a child’s current enrolment and education history.'],
            ['code' => 'education.create', 'name' => 'Record education enrolments', 'description' => 'Open a new education enrolment record for a child.'],
            ['code' => 'education.update', 'name' => 'Edit education enrolments', 'description' => 'Amend the substance of a current education enrolment.'],
            ['code' => 'education.close', 'name' => 'Close education enrolments', 'description' => 'End a current education enrolment with a dated closure.'],

            // --- Assessment ----------------------------------------------------
            ['code' => 'assessments.view', 'name' => 'View assessments', 'description' => 'See completed and in-progress welfare assessments.'],
            ['code' => 'assessments.create', 'name' => 'Conduct assessments', 'description' => 'Record new welfare assessments.'],
            ['code' => 'assessments.update', 'name' => 'Edit assessments', 'description' => 'Amend assessment content.'],
            ['code' => 'assessments.approve', 'name' => 'Approve assessments', 'description' => 'Sign off an assessment, separately from conducting it.'],

            ['code' => 'needs.view', 'name' => 'View identified needs', 'description' => 'See needs identified through assessment.'],
            ['code' => 'needs.create', 'name' => 'Record identified needs', 'description' => 'Add a need identified for a beneficiary.'],
            ['code' => 'needs.update', 'name' => 'Edit identified needs', 'description' => 'Amend a recorded need.'],
            ['code' => 'needs.close', 'name' => 'Close identified needs', 'description' => 'Move a need to a terminal state once it has been addressed.'],

            ['code' => 'interventions.view', 'name' => 'View interventions', 'description' => 'See planned and delivered interventions.'],
            ['code' => 'interventions.create', 'name' => 'Plan interventions', 'description' => 'Create intervention plans.'],
            ['code' => 'interventions.update', 'name' => 'Edit interventions', 'description' => 'Amend intervention plans and record progress.'],
            ['code' => 'interventions.close', 'name' => 'Close interventions', 'description' => 'Complete or cancel an intervention.'],

            // --- Documents ------------------------------------------------------
            // Attachments carry consent forms, evidence and photographs, so
            // viewing them is separated from uploading them.
            ['code' => 'documents.view', 'name' => 'View documents', 'description' => 'View documents attached to a beneficiary record.'],
            ['code' => 'documents.upload', 'name' => 'Upload documents', 'description' => 'Attach documents to a beneficiary record.'],

            // --- Programmes -----------------------------------------------------
            ['code' => 'programmes.view', 'name' => 'View programmes', 'description' => 'See programme definitions and their scope.'],
            ['code' => 'programmes.manage', 'name' => 'Manage programmes', 'description' => 'Create, amend and retire programmes.'],

            // --- Reporting ------------------------------------------------------
            // Reporting and EXPORT are separate on purpose. Export moves
            // identifiable data out of the system, so a viewer is never silently
            // given it: least privilege means the two are granted independently.
            ['code' => 'reports.view', 'name' => 'View reports', 'description' => 'Run and view programme reports.'],
            ['code' => 'reports.export', 'name' => 'Export reports', 'description' => 'Download report data outside ORWeMS. Treated as sensitive and granted explicitly.'],

            // --- Administration -------------------------------------------------
            // Four distinct powers, never one "manage users" umbrella: seeing
            // the staff list must not imply the ability to change it.
            ['code' => 'users.view', 'name' => 'View operator accounts', 'description' => 'See operator accounts and the roles they hold.'],
            ['code' => 'users.create', 'name' => 'Create operator accounts', 'description' => 'Provision new operator accounts. There is no public registration.'],
            ['code' => 'users.update', 'name' => 'Edit operator accounts', 'description' => 'Amend operator account details.'],
            ['code' => 'users.disable', 'name' => 'Disable operator accounts', 'description' => 'Suspend an operator account. Accounts are disabled, never deleted.'],

            // Authority over PEOPLE is deliberately separate from authority over
            // PRIVILEGES. Holding roles.manage lets an administrator change what
            // a role grants; users.assign_roles lets them change who holds it.
            // Combining the two would mean one compromised account could grant
            // itself anything, so the powers are granted independently.
            ['code' => 'users.assign_roles', 'name' => 'Assign roles to operators', 'description' => 'Grant or withdraw a role on an operator account.'],
            ['code' => 'roles.view', 'name' => 'View roles and permissions', 'description' => 'See the role catalogue and the permissions each role confers.'],
            ['code' => 'roles.manage', 'name' => 'Manage roles and permissions', 'description' => 'Create roles and change which permissions a role confers.'],

            // --- System ---------------------------------------------------------
            ['code' => 'system.settings', 'name' => 'Manage system settings', 'description' => 'Change system-wide configuration.'],
            ['code' => 'system.maintenance', 'name' => 'Perform system maintenance', 'description' => 'Run privileged maintenance operations.'],
        ];
    }
}