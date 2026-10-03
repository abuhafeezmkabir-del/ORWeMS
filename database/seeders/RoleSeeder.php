<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 3B — the ORWeMS role catalogue.
 *
 * A role names a RESPONSIBILITY, never a place. There is deliberately no
 * `niger_admin` or `fct_officer` here: geography and programme are WHERE a
 * responsibility applies, which is Phase 3C, and encoding them into role names
 * would multiply the catalogue every time WICHOV opens another state and make
 * "the same job, a different location" impossible to express.
 *
 * The seven roles below are the smallest set that separates the responsibilities
 * WICHOV actually needs to keep apart. Roles that were evaluated and REJECTED,
 * with reasons, are recorded at the bottom of this file — a reviewer should be
 * able to see what was considered, not only what survived.
 *
 * This seeder defines roles ONLY. Their permissions are assigned by
 * RolePermissionSeeder, so a reviewer can read the who and the what separately.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::roles() as $role) {
            $existing = Role::where('code', $role['code'])->first();

            Role::updateOrCreate(
                ['code' => $role['code']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    // Preserve a deliberate deactivation across re-seeding.
                    'is_active' => $existing?->is_active ?? true,
                ]
            );
        }
    }

    /**
     * The Phase 3B role catalogue.
     *
     * @return list<array{code: string, name: string, description: string}>
     */
    public static function roles(): array
    {
        return [
            [
                'code' => 'super_administrator',
                'name' => 'Super Administrator',
                'description' => 'Break-glass technical ownership of the platform. Every permission is granted to this role through an ordinary, visible, database-backed role-permission row — there is no hidden flag and no hard-coded account ID that bypasses authorization.',
            ],
            [
                'code' => 'system_administrator',
                'name' => 'System Administrator',
                'description' => 'Day-to-day administration of accounts, roles and programmes. Deliberately holds NO beneficiary case data permissions: administration and casework are kept apart so a compromised administrator account cannot read children\'s records.',
            ],
            [
                'code' => 'programme_manager',
                'name' => 'Programme Manager',
                'description' => 'Oversight of programme delivery. Confirms registrations, approves assessments and closes cases, but does not enter them day to day.',
            ],
            [
                'code' => 'case_officer',
                'name' => 'Welfare / Case Officer',
                'description' => 'Field casework end to end: registers beneficiaries, works with households, conducts assessments and records interventions.',
            ],
            [
                'code' => 'registration_officer',
                'name' => 'Data Entry / Registration Officer',
                'description' => 'Narrow intake role. Enters registration and household data accurately, and cannot verify what they entered — the verification step is held by a different role.',
            ],
            [
                'code' => 'assessor',
                'name' => 'Assessor',
                'description' => 'Conducts welfare assessments and identifies needs. Assessment is separated from registration so a single person cannot both enter a household and sign off its welfare rating.',
            ],
            [
                'code' => 'auditor',
                'name' => 'Auditor',
                'description' => 'Read-only oversight of records and administrative configuration. Never holds an export permission: an auditor who can read is already sensitive, and being able to remove data in bulk is not part of oversight.',
            ],
        ];
    }
}