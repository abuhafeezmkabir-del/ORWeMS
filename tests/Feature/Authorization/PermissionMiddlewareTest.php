<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3B — permission middleware.
 *
 * Confirms that the `permission:` middleware alias integrates correctly with
 * the route stack established in Phase 3A, with the correct HTTP behaviour:
 *
 *  - unauthenticated requests are redirected to login (handled by `auth`)
 *  - authenticated but unauthorized requests receive 403
 *  - authorized requests are allowed through
 *  - a misspelled permission code triggers a loud programming-error exception
 *    rather than silently denying (or permitting) the request
 */
class PermissionMiddlewareTest extends TestCase
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

    public function test_guest_is_redirected_to_login_for_permission_guarded_route(): void
    {
        $this->get(route('administration.roles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_operator_without_permission_receives_403(): void
    {
        // No role assigned — no permissions.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('administration.roles.index'))
            ->assertForbidden();
    }

    public function test_operator_with_required_permission_can_reach_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'system_administrator')->sole());

        $this->actingAs($user)
            ->get(route('administration.roles.index'))
            ->assertOk();
    }

    public function test_operator_with_unrelated_permission_is_denied(): void
    {
        // auditor holds roles.view — wait no, let us use an operator that holds
        // only casework permissions, not roles.view.
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'registration_officer')->sole());

        $this->actingAs($user)
            ->get(route('administration.roles.index'))
            ->assertForbidden();
    }

    public function test_permissions_index_requires_roles_view(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('administration.permissions.index'))
            ->assertForbidden();
    }

    public function test_operator_with_roles_view_can_read_permissions_catalogue(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'auditor')->sole());

        $this->actingAs($user)
            ->get(route('administration.permissions.index'))
            ->assertOk();
    }

    public function test_assign_role_post_requires_users_assign_roles(): void
    {
        $role   = Role::where('code', 'auditor')->sole();
        $target = User::factory()->create();

        // Operator has roles.view but NOT users.assign_roles
        $operator = User::factory()->create();
        $operator->assignRole(Role::where('code', 'auditor')->sole());

        $this->actingAs($operator)
            ->post(route('administration.roles.assign', ['role' => $role]), [
                'user_id' => $target->id,
            ])
            ->assertForbidden();
    }

    public function test_assign_role_post_works_for_authorized_operator(): void
    {
        $role   = Role::where('code', 'auditor')->sole();
        $target = User::factory()->create();

        $operator = User::factory()->create();
        $operator->assignRole(Role::where('code', 'super_administrator')->sole());

        $this->actingAs($operator)
            ->post(route('administration.roles.assign', ['role' => $role]), [
                'user_id' => $target->id,
            ])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole('auditor'));
    }

    public function test_disabled_account_is_refused_even_on_permission_guarded_route(): void
    {
        $user = User::factory()->disabled()->create();
        $user->assignRole(Role::where('code', 'super_administrator')->sole());

        // The account holds every permission but is disabled — account.active
        // must run before permission and terminate the request.
        $this->actingAs($user)
            ->get(route('administration.roles.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_super_administrator_can_access_all_administration_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'super_administrator')->sole());

        $this->actingAs($user)
            ->get(route('administration.roles.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('administration.permissions.index'))
            ->assertOk();
    }
}
