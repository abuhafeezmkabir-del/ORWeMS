<?php

namespace Tests\Feature\Authorization;

use App\Models\Country;
use App\Models\State;
use App\Models\User;
use App\Models\UserGeographicScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Authorization\Concerns\BuildsScopeFixtures;
use Tests\TestCase;

/**
 * Phase 3C — database integrity of the scope tables.
 *
 * Application validation is a convenience; these tests bypass it and write
 * straight to the database, proving the engine itself refuses a duplicate
 * assignment, a dangling reference, a silent cascade, and a scope row whose
 * `level` disagrees with the area it names.
 *
 * The CHECK-constraint tests are MySQL-only. SQLite cannot add a CHECK to an
 * existing table, so on that engine the identical rule is enforced by the model
 * guard, which is covered separately below — the same split Phase 2.1 used.
 */
class ScopeDatabaseIntegrityTest extends TestCase
{
    use BuildsScopeFixtures, RefreshDatabase;

    private function rawProgrammeUser(array $row): void
    {
        DB::table('programme_user')->insert($row + [
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rawGeographicScope(array $row): void
    {
        DB::table('user_geographic_scopes')->insert($row + [
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // programme_user
    // -------------------------------------------------------------------------

    public function test_the_same_programme_cannot_be_assigned_to_the_same_account_twice(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();

        $user->assignProgrammeScope($programme);

        $this->expectException(QueryException::class);

        $this->rawProgrammeUser([
            'user_id' => $user->getKey(),
            'programme_id' => $programme->getKey(),
        ]);
    }

    public function test_an_assignment_cannot_reference_a_nonexistent_account(): void
    {
        $programme = $this->makeProgramme();

        $this->expectException(QueryException::class);

        $this->rawProgrammeUser([
            'user_id' => 999999,
            'programme_id' => $programme->getKey(),
        ]);
    }

    public function test_an_assignment_cannot_reference_a_nonexistent_programme(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        $this->rawProgrammeUser([
            'user_id' => $user->getKey(),
            'programme_id' => 999999,
        ]);
    }

    public function test_a_programme_that_still_has_assignments_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();

        $user->assignProgrammeScope($programme);

        // RESTRICT: the record of who was assigned must not disappear with the
        // programme, however the delete was attempted.
        $this->expectException(QueryException::class);

        $programme->delete();
    }

    public function test_an_account_that_still_has_assignments_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $user->assignProgrammeScope($this->makeProgramme());

        $this->expectException(QueryException::class);

        $user->delete();
    }

    // -------------------------------------------------------------------------
    // user_geographic_scopes
    // -------------------------------------------------------------------------

    public function test_the_same_area_cannot_be_granted_twice_at_the_same_level(): void
    {
        $user = User::factory()->create();
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        $user->assignGeographicScope($state);

        $this->expectException(QueryException::class);

        $this->rawGeographicScope([
            'user_id' => $user->getKey(),
            'level' => 'state',
            'state_id' => $state->getKey(),
        ]);
    }

    public function test_the_same_account_may_hold_several_levels_at_once(): void
    {
        $user = User::factory()->create();
        $country = $this->makeCountry();
        $state = $this->makeState($country, 'Niger State');
        $lga = $this->makeLga($state, 'Chanchaga');

        $user->assignGeographicScope($country);
        $user->assignGeographicScope($state);
        $user->assignGeographicScope($lga);

        // The per-level unique indexes refuse a duplicate; they must not refuse
        // an account holding the country, a state and an LGA together.
        $this->assertDatabaseCount('user_geographic_scopes', 3);
        $this->assertSame(3, $user->geographicScopes()->count());
    }

    public function test_different_accounts_may_hold_the_same_area(): void
    {
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        $first = User::factory()->create();
        $second = User::factory()->create();

        $first->assignGeographicScope($state);
        $second->assignGeographicScope($state);

        $this->assertDatabaseCount('user_geographic_scopes', 2);
    }

    public function test_a_scope_cannot_reference_a_nonexistent_area(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        $this->rawGeographicScope([
            'user_id' => $user->getKey(),
            'level' => 'state',
            'state_id' => 999999,
        ]);
    }

    public function test_a_scoped_area_cannot_be_deleted(): void
    {
        $user = User::factory()->create();

        // Deliberately a state with no children, so the refusal can only come
        // from the scope table and not from a child geography row.
        $state = $this->makeState($this->makeCountry(), 'Restrict Me State');

        $user->assignGeographicScope($state);

        $this->expectException(QueryException::class);

        $state->delete();
    }

    // -------------------------------------------------------------------------
    // Model guard (both engines)
    // -------------------------------------------------------------------------

    public function test_a_scope_naming_two_areas_is_refused(): void
    {
        $user = User::factory()->create();
        $state = $this->makeState($this->makeCountry(), 'Niger State');
        $lga = $this->makeLga($state, 'Chanchaga');

        $this->expectException(ValidationException::class);

        $user->geographicScopes()->create([
            'level' => 'state',
            'state_id' => $state->getKey(),
            'local_government_area_id' => $lga->getKey(),
        ]);
    }

    public function test_a_scope_whose_level_disagrees_with_its_area_is_refused(): void
    {
        $user = User::factory()->create();
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        $this->expectException(ValidationException::class);

        $user->geographicScopes()->create([
            'level' => 'lga',
            'state_id' => $state->getKey(),
        ]);
    }

    public function test_a_scope_naming_no_area_is_refused(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        $user->geographicScopes()->create(['level' => 'state']);
    }

    public function test_an_account_may_hold_a_country_and_a_state_without_ambiguity(): void
    {
        $user = User::factory()->create();
        $state = $this->makeState($this->makeCountry(), 'Niger State');
        $country = Country::query()->whereKey($state->country_id)->sole();

        $user->assignGeographicScope($country);
        $user->assignGeographicScope($state);

        $scopes = $user->geographicScopes()->orderBy('level')->get();

        $this->assertCount(2, $scopes);
        $this->assertSame('country', $scopes->first()->level->value);
        $this->assertSame('state', $scopes->last()->level->value);

        // Each row names exactly one area.
        foreach ($scopes as $scope) {
            $this->assertInstanceOf(UserGeographicScope::class, $scope);
            $this->assertSame(1, collect($scope->getAttributes())
                ->only(['country_id', 'state_id', 'local_government_area_id', 'ward_id', 'community_id'])
                ->filter(static fn ($value): bool => $value !== null)
                ->count());
        }
    }

    // -------------------------------------------------------------------------
    // MySQL-only CHECK constraint
    // -------------------------------------------------------------------------

    public function test_the_database_itself_rejects_a_scope_with_no_area(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only CHECK constraint; the model guard is tested above.');
        }

        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        $this->rawGeographicScope([
            'user_id' => $user->getKey(),
            'level' => 'state',
        ]);
    }

    public function test_the_database_itself_rejects_a_scope_with_two_areas(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only CHECK constraint; the model guard is tested above.');
        }

        $user = User::factory()->create();
        $state = $this->makeState($this->makeCountry(), 'Niger State');
        $lga = $this->makeLga($state, 'Chanchaga');

        $this->expectException(QueryException::class);

        $this->rawGeographicScope([
            'user_id' => $user->getKey(),
            'level' => 'state',
            'state_id' => $state->getKey(),
            'local_government_area_id' => $lga->getKey(),
        ]);
    }

    public function test_the_database_itself_rejects_a_level_that_disagrees_with_its_area(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only CHECK constraint; the model guard is tested above.');
        }

        $user = User::factory()->create();
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        $this->expectException(QueryException::class);

        $this->rawGeographicScope([
            'user_id' => $user->getKey(),
            'level' => 'lga',
            'state_id' => $state->getKey(),
        ]);
    }

    public function test_the_database_itself_rejects_an_unknown_level(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only CHECK constraint; the model guard is tested above.');
        }

        $user = User::factory()->create();
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        $this->expectException(QueryException::class);

        $this->rawGeographicScope([
            'user_id' => $user->getKey(),
            'level' => 'planet',
            'state_id' => $state->getKey(),
        ]);
    }

    public function test_the_database_itself_accepts_every_well_formed_level(): void
    {
        $user = User::factory()->create();
        $country = $this->makeCountry();
        $state = $this->makeState($country, 'Niger State');
        $lga = $this->makeLga($state, 'Chanchaga');
        $ward = $this->makeWard($lga, 'Ward A1');
        $community = $this->makeCommunity($lga, $ward, 'Community A1a');

        $this->rawGeographicScope(['user_id' => $user->getKey(), 'level' => 'country', 'country_id' => $country->getKey()]);
        $this->rawGeographicScope(['user_id' => $user->getKey(), 'level' => 'state', 'state_id' => $state->getKey()]);
        $this->rawGeographicScope(['user_id' => $user->getKey(), 'level' => 'lga', 'local_government_area_id' => $lga->getKey()]);
        $this->rawGeographicScope(['user_id' => $user->getKey(), 'level' => 'ward', 'ward_id' => $ward->getKey()]);
        $this->rawGeographicScope(['user_id' => $user->getKey(), 'level' => 'community', 'community_id' => $community->getKey()]);

        // The constraint must refuse malformed rows without refusing valid ones.
        $this->assertDatabaseCount('user_geographic_scopes', 5);
    }
}
