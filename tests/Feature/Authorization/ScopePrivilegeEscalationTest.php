<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\UserGeographicScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Authorization\Concerns\BuildsScopeFixtures;
use Tests\TestCase;

/**
 * Phase 3C — privileges cannot be escalated through scope.
 *
 * Phase 3C introduces no scope-administration screen and no new route, so the
 * first question these tests answer is whether any escalation path exists at all:
 * they enumerate the registered routes, probe plausible scope URLs, and then
 * throw forged payloads at the one write endpoint that does exist to confirm the
 * extra fields are ignored rather than merged.
 *
 * The last tests re-establish the ordering that makes all of this safe — a
 * disabled account is stopped before scope is ever consulted, and a guest is
 * stopped before that.
 */
class ScopePrivilegeEscalationTest extends TestCase
{
    use BuildsScopeFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_no_route_administers_access_scope(): void
    {
        $offending = [];

        foreach (Route::getRoutes() as $route) {
            $uri = strtolower($route->uri());

            foreach (['scope', 'programme_user', 'geographic'] as $needle) {
                if (str_contains($uri, $needle)) {
                    $offending[] = $uri;
                }
            }
        }

        // Scope administration is deliberately deferred. If a route appears here,
        // this phase grew an administration surface that has no tests protecting
        // it yet.
        $this->assertSame([], $offending, 'No route may administer access scope in Phase 3C.');
    }

    public function test_plausible_scope_administration_urls_do_not_exist(): void
    {
        $attempts = [
            ['get', '/administration/scopes'],
            ['post', '/administration/scopes'],
            ['get', '/administration/scope'],
            ['get', '/administration/programmes/scope'],
            ['get', '/administration/geographic-scopes'],
            ['post', '/administration/users/1/scopes'],
            ['post', '/administration/users/1/programmes'],
            ['patch', '/users/1/scope'],
            ['get', '/scopes'],
        ];

        foreach ($attempts as [$method, $uri]) {
            $this->{$method}($uri)->assertNotFound();
        }

        $this->assertDatabaseCount('user_geographic_scopes', 0);
        $this->assertDatabaseCount('programme_user', 0);
    }

    public function test_scope_is_not_mass_assignable_on_the_user_model(): void
    {
        $user = User::factory()->create();

        $user->fill([
            'programmes' => [1],
            'geographicScopes' => [1],
            'roles' => [1],
            'role' => 1,
            'level' => 'country',
        ]);
        $user->save();

        $this->assertDatabaseCount('programme_user', 0);
        $this->assertDatabaseCount('user_geographic_scopes', 0);
        $this->assertDatabaseCount('role_user', 0);
    }

    public function test_the_owner_of_a_scope_cannot_be_reassigned_by_mass_assignment(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $country = $this->makeCountry();

        $scope = $owner->geographicScopes()->create([
            'level' => 'country',
            'country_id' => $country->getKey(),
        ]);

        $scope->fill(['user_id' => $other->getKey()]);
        $scope->save();
        $scope->refresh();

        // Ownership comes from the relationship. A payload cannot hand someone
        // else's scope to a different account.
        $this->assertSame((int) $owner->getKey(), (int) $scope->user_id);
    }

    public function test_a_scope_cannot_be_deactivated_by_mass_assignment(): void
    {
        $owner = User::factory()->create();
        $country = $this->makeCountry();

        $scope = $owner->geographicScopes()->create([
            'level' => 'country',
            'country_id' => $country->getKey(),
        ]);

        $scope->fill(['is_active' => false]);
        $scope->save();
        $scope->refresh();

        $this->assertTrue($scope->is_active);
    }

    public function test_scope_is_not_mass_assignable_through_a_scope_record(): void
    {
        $country = $this->makeCountry();

        // `level` and the area column ARE fillable — that is how an assignment is
        // made — so this asserts the inverse: nothing else on the row follows
        // from input.
        $scope = new UserGeographicScope([
            'level' => 'country',
            'country_id' => $country->getKey(),
            'user_id' => 999999,
            'is_active' => true,
        ]);

        $attributes = $scope->getAttributes();

        $this->assertArrayNotHasKey('user_id', $attributes);
        $this->assertArrayNotHasKey('is_active', $attributes);
    }

    public function test_forged_scope_fields_in_a_role_assignment_request_are_ignored(): void
    {
        $programme = $this->makeProgramme();
        $country = $this->makeCountry();

        $operator = User::factory()->create();
        $operator->assignRole(Role::where('code', 'super_administrator')->sole());

        $target = User::factory()->create();
        $role = Role::where('code', 'auditor')->sole();

        $this->actingAs($operator)
            ->post(route('administration.roles.assign', ['role' => $role]), [
                'user_id' => $target->getKey(),
                // Everything below is scope, not role.
                'programme_id' => $programme->getKey(),
                'country_id' => $country->getKey(),
                'state_id' => $country->states()->first()?->getKey() ?? 1,
                'level' => 'country',
                'scopes' => [1, 2, 3],
                'geographicScopes' => [1],
            ])
            ->assertRedirect();

        // Only the role assignment happened. The extra fields were dropped.
        $this->assertTrue($target->fresh()->hasRole('auditor'));
        $this->assertDatabaseCount('programme_user', 0);
        $this->assertDatabaseCount('user_geographic_scopes', 0);
    }

    public function test_an_operator_without_the_permission_cannot_assign_scope_implicitly(): void
    {
        $programme = $this->makeProgramme();

        // `auditor` holds roles.view but not users.assign_roles.
        $attacker = User::factory()->create();
        $attacker->assignRole(Role::where('code', 'auditor')->sole());

        $victim = User::factory()->create();
        $role = Role::where('code', 'super_administrator')->sole();

        $this->actingAs($attacker)
            ->post(route('administration.roles.assign', ['role' => $role]), [
                'user_id' => $victim->getKey(),
                'programme_id' => $programme->getKey(),
            ])
            ->assertForbidden();

        // A denied request leaves no partial write behind it.
        $this->assertFalse($victim->fresh()->hasRole('super_administrator'));
        $this->assertDatabaseCount('programme_user', 0);
        $this->assertDatabaseCount('user_geographic_scopes', 0);
    }

    public function test_a_guest_cannot_reach_the_administration_surface(): void
    {
        $this->get(route('administration.roles.index'))->assertRedirect(route('login'));

        $this->post(route('administration.roles.assign', ['role' => 1]))->assertRedirect(route('login'));

        $this->assertDatabaseCount('programme_user', 0);
        $this->assertDatabaseCount('user_geographic_scopes', 0);
    }

    public function test_a_disabled_operator_is_refused_even_while_holding_role_and_scope(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'super_administrator')->sole());
        $user->assignProgrammeScope($this->makeProgramme());
        $user->assignGeographicScope($this->makeCountry());

        $this->actingAs($user)->get(route('administration.roles.index'))->assertOk();

        $user->forceFill(['status' => UserStatus::Disabled])->save();
        $this->app['auth']->forgetGuards();

        // Account state is checked before scope is ever consulted, so a
        // fully-scoped but disabled operator is still turned away.
        $this->actingAs($user)->get(route('administration.roles.index'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Disabling is a status change, not a purge: the assignments survive for
        // the day the account is reactivated.
        $this->assertDatabaseCount('programme_user', 1);
        $this->assertDatabaseCount('user_geographic_scopes', 1);
    }
}
