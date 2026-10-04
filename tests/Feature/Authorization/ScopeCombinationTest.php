<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\BuildsScopeFixtures;
use Tests\TestCase;

/**
 * Phase 3C — programme scope and geographic scope together.
 *
 * The documented combination rule is: WHEN BOTH DIMENSIONS APPLY TO A RECORD,
 * BOTH MUST BE HELD. A programme assignment does not widen geographic reach, and
 * a geographic assignment does not widen programme reach. The two are
 * independent filters that both have to pass.
 *
 * These tests exist because the alternative reading — treating a programme
 * assignment as "everywhere in that programme" — would silently hand an operator
 * every state the moment they were given one programme, which is exactly the
 * kind of quiet widening that makes humanitarian data leak.
 */
class ScopeCombinationTest extends TestCase
{
    use BuildsScopeFixtures, RefreshDatabase;

    public function test_a_check_with_neither_dimension_is_refused(): void
    {
        $user = User::factory()->create();

        // Nothing to check is a caller mistake, not permission to proceed.
        $this->assertFalse($user->hasScopeFor());
    }

    public function test_a_programme_only_record_needs_only_programme_scope(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();

        $user->assignProgrammeScope($programme);

        $this->assertTrue($user->hasScopeFor($programme));
        $this->assertTrue($user->hasScopeFor($programme, null));
    }

    public function test_a_geography_only_record_needs_only_geographic_scope(): void
    {
        $user = User::factory()->create();
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        $user->assignGeographicScope($state);

        $this->assertTrue($user->hasScopeFor(null, $state));
    }

    public function test_a_record_in_both_dimensions_needs_both(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        $user->assignProgrammeScope($programme);
        $user->assignGeographicScope($state);

        $this->assertTrue($user->hasScopeFor($programme, $state));
    }

    public function test_the_right_programme_in_the_wrong_place_is_refused(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();
        $country = $this->makeCountry();
        $niger = $this->makeState($country, 'Niger State');
        $nasarawa = $this->makeState($country, 'Nasarawa State');

        $user->assignProgrammeScope($programme);
        $user->assignGeographicScope($niger);

        $this->assertTrue($user->hasScopeFor($programme, $niger));
        $this->assertFalse($user->hasScopeFor($programme, $nasarawa));
    }

    public function test_the_right_place_under_the_wrong_programme_is_refused(): void
    {
        $user = User::factory()->create();
        $pilot = $this->makeProgramme('WICHOV-PILOT');
        $other = $this->makeProgramme('WICHOV-OTHER');
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        $user->assignProgrammeScope($pilot);
        $user->assignGeographicScope($state);

        $this->assertFalse($user->hasScopeFor($other, $state));
    }

    public function test_a_geographic_scope_does_not_widen_programme_reach(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();
        $country = $this->makeCountry();
        $state = $this->makeState($country, 'Niger State');

        // Scoped to the entire country, and still unable to reach a programme it
        // was never assigned.
        $user->assignGeographicScope($country);

        $this->assertTrue($user->hasGeographicAccess($state));
        $this->assertFalse($user->hasScopeFor($programme, $state));
    }

    public function test_permission_and_scope_are_separate_facts_both_required(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class]);

        $programme = $this->makeProgramme();
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        // Capability, but no reach.
        $withoutScope = User::factory()->create();
        $withoutScope->assignRole(Role::where('code', 'case_officer')->sole());

        // Reach, but no capability.
        $withoutPermission = User::factory()->create();
        $withoutPermission->assignProgrammeScope($programme);
        $withoutPermission->assignGeographicScope($state);

        $this->assertTrue($withoutScope->hasPermission('beneficiaries.view'));
        $this->assertFalse($withoutScope->hasScopeFor($programme, $state));

        $this->assertTrue($withoutPermission->hasScopeFor($programme, $state));
        $this->assertFalse($withoutPermission->hasPermission('beneficiaries.view'));
    }
}
