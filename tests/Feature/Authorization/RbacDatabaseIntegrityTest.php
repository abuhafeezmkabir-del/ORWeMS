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
 * Phase 3B — database integrity.
 *
 * Verifies schema-level guarantees on the RBAC tables:
 *
 *  - unique constraints on role code, permission code, user-role pair and
 *    role-permission pair are enforced by the database engine, not just by
 *    application logic.
 *  - Foreign keys are enforced: an assignment that references a non-existent
 *    user, role or permission is refused.
 *  - Deletion policies are correct: no silent cascades.
 */
class RbacDatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_role_code_must_be_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('roles')->insert([
            'name'       => 'Duplicate Role',
            'code'       => 'auditor', // already seeded
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_permission_code_must_be_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('permissions')->insert([
            'name'       => 'Duplicate Permission',
            'code'       => 'beneficiaries.view', // already seeded
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_role_user_pair_must_be_unique_at_database_level(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'auditor')->sole();

        $user->assignRole($role);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('role_user')->insert([
            'user_id'    => $user->id,
            'role_id'    => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_permission_role_pair_must_be_unique_at_database_level(): void
    {
        $role       = Role::where('code', 'auditor')->sole();
        $permission = $role->permissions()->first();

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('permission_role')->insert([
            'role_id'       => $role->id,
            'permission_id' => $permission->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_foreign_key_on_role_user_rejects_nonexistent_user(): void
    {
        $role = Role::where('code', 'auditor')->sole();

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('role_user')->insert([
            'user_id'    => 99999,
            'role_id'    => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_foreign_key_on_role_user_rejects_nonexistent_role(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('role_user')->insert([
            'user_id'    => $user->id,
            'role_id'    => 99999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_foreign_key_on_permission_role_rejects_nonexistent_permission(): void
    {
        $role = Role::where('code', 'auditor')->sole();

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('permission_role')->insert([
            'role_id'       => $role->id,
            'permission_id' => 99999,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_restrict_delete_prevents_role_deletion_while_assigned(): void
    {
        $user = User::factory()->create();
        $role = Role::where('code', 'auditor')->sole();

        $user->assignRole($role);

        // RESTRICT means the database refuses to delete a role that still has
        // pivot rows pointing to it.
        $this->expectException(\Illuminate\Database\QueryException::class);

        $role->delete();
    }

    public function test_restrict_delete_prevents_permission_deletion_while_assigned(): void
    {
        $role       = Role::where('code', 'auditor')->sole();
        $permission = $role->permissions()->first();

        $this->expectException(\Illuminate\Database\QueryException::class);

        $permission->delete();
    }
}
