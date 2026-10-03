<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3B — RBAC seeder integrity.
 *
 * Verifies that:
 *  - the seeder produces a stable, predictable vocabulary
 *  - repeated seeding creates no duplicates
 *  - counts match the declared catalogues
 *  - the permission/role codes match what PermissionCatalog exposes
 *  - role-permission assignments are consistent with RolePermissionSeeder
 *  - no domain records are created (beneficiaries, households, etc.)
 */
class RbacSeederTest extends TestCase
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

    public function test_expected_number_of_roles_are_seeded(): void
    {
        $expected = count(RoleSeeder::roles());

        $this->assertDatabaseCount('roles', $expected);
        $this->assertSame($expected, Role::count());
    }

    public function test_expected_number_of_permissions_are_seeded(): void
    {
        $expected = count(PermissionSeeder::permissions());

        $this->assertDatabaseCount('permissions', $expected);
        $this->assertSame($expected, Permission::count());
    }

    public function test_permission_codes_are_unique(): void
    {
        $codes = Permission::pluck('code');

        $this->assertCount($codes->unique()->count(), $codes->all());
    }

    public function test_role_codes_are_unique(): void
    {
        $codes = Role::pluck('code');

        $this->assertCount($codes->unique()->count(), $codes->all());
    }

    public function test_permission_catalog_recognises_all_seeded_codes(): void
    {
        // Flush the cache so it is re-read from the (refreshed) test database.
        PermissionCatalog::flush();

        foreach (PermissionSeeder::permissions() as $row) {
            $this->assertTrue(
                PermissionCatalog::recognises($row['code']),
                "PermissionCatalog should recognise [{$row['code']}]."
            );
        }
    }

    public function test_permission_catalog_does_not_recognise_invented_codes(): void
    {
        PermissionCatalog::flush();

        $this->assertFalse(PermissionCatalog::recognises('invented.capability'));
        $this->assertFalse(PermissionCatalog::recognises(''));
        $this->assertFalse(PermissionCatalog::recognises('beneficiaries'));
    }

    public function test_every_seeded_role_exists_and_is_active(): void
    {
        foreach (RoleSeeder::roles() as $row) {
            $role = Role::where('code', $row['code'])->first();

            $this->assertNotNull($role, "Role [{$row['code']}] should exist.");
            $this->assertTrue($role->is_active, "Role [{$row['code']}] should be active after seeding.");
        }
    }

    public function test_seeding_twice_produces_no_duplicates(): void
    {
        // Record counts after the first seed (done in setUp)
        $roleCount       = Role::count();
        $permissionCount = Permission::count();

        // Seed again
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->assertSame($roleCount, Role::count(), 'No duplicate roles after second seed.');
        $this->assertSame($permissionCount, Permission::count(), 'No duplicate permissions after second seed.');
    }

    public function test_seeder_creates_no_domain_records(): void
    {
        // Nothing in the humanitarian domain should be fabricated by RBAC seeders.
        $this->assertDatabaseEmpty('beneficiaries');
        $this->assertDatabaseEmpty('households');
        $this->assertDatabaseEmpty('persons');
        $this->assertDatabaseEmpty('assessments');
        $this->assertDatabaseEmpty('needs');
        $this->assertDatabaseEmpty('interventions');
    }

    public function test_super_administrator_has_all_permissions_in_pivot(): void
    {
        $role = Role::where('code', 'super_administrator')->sole();

        $permissionCount = Permission::count();
        $grantedCount    = $role->permissions()->count();

        $this->assertSame(
            $permissionCount,
            $grantedCount,
            'Super administrator pivot must reference every permission row.'
        );
    }

    public function test_all_role_permission_mapping_codes_are_valid(): void
    {
        $validCodes = Permission::pluck('code')->all();

        foreach (RolePermissionSeeder::mapping() as $roleCode => $permCodes) {
            foreach ($permCodes as $code) {
                $this->assertContains(
                    $code,
                    $validCodes,
                    "Mapping for role [{$roleCode}] references undefined permission [{$code}]."
                );
            }
        }
    }
}
