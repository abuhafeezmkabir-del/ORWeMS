<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3B — RBAC relationships.
 *
 * Verifies the structural claims made in the RBAC design:
 *
 *  - Many-to-many user/role and role/permission relationships work correctly.
 *  - Uniqueness constraints prevent duplicate assignments.
 *  - Inactive roles and permissions are transparent to the permission system.
 *  - hasPermission() and hasRole() work as documented.
 */
class RbacRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the vocabulary once; individual tests create their own fixtures.
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    // -------------------------------------------------------------------------
    // Role/user relationships
    // -------------------------------------------------------------------------

    public function test_a_user_can_have_multiple_roles(): void
    {
        $user = User::factory()->create();
        $r1   = Role::where('code', 'case_officer')->sole();
        $r2   = Role::where('code', 'assessor')->sole();

        $user->assignRole($r1);
        $user->assignRole($r2);

        $this->assertCount(2, $user->fresh()->roles);
        $this->assertTrue($user->hasRole('case_officer'));
        $this->assertTrue($user->hasRole('assessor'));
        $this->assertFalse($user->hasRole('auditor'));
    }

    public function test_a_role_can_have_multiple_users(): void
    {
        $role = Role::where('code', 'auditor')->sole();
        $u1   = User::factory()->create();
        $u2   = User::factory()->create();

        $u1->assignRole($role);
        $u2->assignRole($role);

        $this->assertCount(2, $role->fresh()->users);
    }

    public function test_duplicate_role_assignment_is_idempotent(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'auditor')->sole();

        $user->assignRole($role);
        $user->assignRole($role); // should not throw

        $this->assertCount(1, $user->fresh()->roles);
        $this->assertDatabaseCount('role_user', 1);
    }

    public function test_duplicate_user_role_pair_is_rejected_at_database_level(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'assessor')->sole();

        $user->assignRole($role);

        // The unique constraint must refuse a bare INSERT, not just the Eloquent
        // helper that wraps syncWithoutDetaching.
        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('role_user')->insert([
            'user_id'    => $user->id,
            'role_id'    => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Role/permission relationships
    // -------------------------------------------------------------------------

    public function test_a_role_has_the_permissions_the_seeder_assigned_to_it(): void
    {
        $role = Role::where('code', 'auditor')->sole();

        // Every auditor permission code ends in ".view"
        foreach ($role->permissions as $permission) {
            $this->assertTrue(
                str_ends_with($permission->code, '.view'),
                "Auditor was granted [{$permission->code}] which is not a view permission."
            );
        }

        // Auditor must have at least one permission
        $this->assertNotEmpty($role->permissions);
    }

    public function test_a_permission_can_belong_to_multiple_roles(): void
    {
        // beneficiaries.view is granted to several roles
        $permission = Permission::where('code', 'beneficiaries.view')->sole();

        $this->assertGreaterThan(1, $permission->roles->count());
    }

    public function test_duplicate_role_permission_pair_is_rejected_at_database_level(): void
    {
        $role       = Role::where('code', 'auditor')->sole();
        $permission = $role->permissions->first();

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('permission_role')->insert([
            'role_id'       => $role->id,
            'permission_id' => $permission->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Permission resolution
    // -------------------------------------------------------------------------

    public function test_a_user_with_a_permission_has_that_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'auditor')->sole();

        $user->assignRole($role);

        $this->assertTrue($user->hasPermission('beneficiaries.view'));
        $this->assertTrue($user->can('beneficiaries.view'));
    }

    public function test_a_user_without_a_permission_does_not_have_it(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'auditor')->sole();

        $user->assignRole($role);

        // Auditor holds no write permissions
        $this->assertFalse($user->hasPermission('beneficiaries.create'));
        $this->assertFalse($user->can('beneficiaries.create'));
    }

    public function test_a_user_with_no_roles_has_no_permissions(): void
    {
        $user = User::factory()->create();

        $this->assertEmpty($user->permissionCodes());
        $this->assertFalse($user->hasPermission('beneficiaries.view'));
    }

    public function test_multiple_roles_combine_their_permissions(): void
    {
        $user      = User::factory()->create();
        $assessor  = Role::where('code', 'assessor')->sole();
        $caseOfficer = Role::where('code', 'case_officer')->sole();

        $user->assignRole($assessor);
        $user->assignRole($caseOfficer);

        // case_officer holds beneficiaries.create; assessor does not.
        // Together the user should hold it.
        $this->assertTrue($user->hasPermission('beneficiaries.create'));

        // Both roles hold beneficiaries.view; it must still appear once
        $this->assertCount(
            1,
            $user->permissionCodes()->filter(fn ($c) => $c === 'beneficiaries.view')
        );
    }

    public function test_an_inactive_role_confers_no_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'auditor')->sole();

        $user->assignRole($role);

        // Deactivate the role without touching the pivot row
        $role->forceFill(['is_active' => false])->save();

        $this->assertEmpty($user->permissionCodes());
        $this->assertFalse($user->hasPermission('beneficiaries.view'));
    }

    public function test_an_inactive_permission_is_not_granted(): void
    {
        $user       = User::factory()->create();
        $role       = Role::where('code', 'auditor')->sole();
        $permission = Permission::where('code', 'beneficiaries.view')->sole();

        $user->assignRole($role);
        $this->assertTrue($user->hasPermission('beneficiaries.view'));

        // Retire the permission without touching any pivot row
        $permission->forceFill(['is_active' => false])->save();

        $this->assertFalse($user->hasPermission('beneficiaries.view'));
    }

    public function test_an_unknown_permission_code_returns_false(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'super_administrator')->sole();

        $user->assignRole($role);

        // Even a super administrator does not have an invented code
        $this->assertFalse($user->hasPermission('invented.capability'));
        $this->assertFalse($user->can('invented.capability'));
    }

    // -------------------------------------------------------------------------
    // Role catalogue
    // -------------------------------------------------------------------------

    public function test_super_administrator_holds_every_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'super_administrator')->sole();

        $user->assignRole($role);

        $allCodes = Permission::query()->pluck('code');

        foreach ($allCodes as $code) {
            $this->assertTrue(
                $user->hasPermission($code),
                "Super administrator should hold [{$code}]."
            );
        }
    }

    public function test_system_administrator_has_no_beneficiary_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'system_administrator')->sole();

        $user->assignRole($role);

        $this->assertFalse($user->hasPermission('beneficiaries.view'));
        $this->assertFalse($user->hasPermission('beneficiaries.create'));
        $this->assertFalse($user->hasPermission('households.view'));
        $this->assertFalse($user->hasPermission('documents.view'));
    }

    public function test_auditor_holds_only_view_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'auditor')->sole();

        $user->assignRole($role);

        foreach ($user->permissionCodes() as $code) {
            $this->assertTrue(
                str_ends_with($code, '.view'),
                "Auditor must only hold view permissions; found [{$code}]."
            );
        }
    }

    public function test_auditor_cannot_export_reports(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'auditor')->sole());

        $this->assertFalse($user->hasPermission('reports.export'));
    }

    public function test_registration_officer_cannot_verify_what_they_register(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'registration_officer')->sole());

        $this->assertTrue($user->hasPermission('beneficiaries.create'));
        $this->assertFalse($user->hasPermission('beneficiaries.verify'));
    }

    public function test_assessor_cannot_approve_their_own_assessments(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'assessor')->sole());

        $this->assertTrue($user->hasPermission('assessments.create'));
        $this->assertFalse($user->hasPermission('assessments.approve'));
    }

    public function test_programme_manager_can_approve_but_not_enter_assessments(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'programme_manager')->sole());

        $this->assertTrue($user->hasPermission('assessments.approve'));
        $this->assertFalse($user->hasPermission('assessments.create'));
    }
}
