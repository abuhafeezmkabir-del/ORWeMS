<?php

namespace Tests\Feature\Authorization;

use App\Enums\GeographicScopeLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\BuildsScopeFixtures;
use Tests\TestCase;

/**
 * Phase 3C — geographic scope, and the hierarchy it inherits.
 *
 * Two geographic facts are easy to get wrong and expensive to get wrong:
 *
 *  1. A scope at one level must reach every descendant level, without those
 *     descendants being copied into the scope table.
 *  2. It must reach nothing else — not a sibling branch, and not the level above
 *     it. A Niger State scope is not "everything", and a ward scope is not a
 *     licence to read the whole LGA.
 *
 * The fixture is two states in one country, each with its own LGA/ward/community
 * branch, so every test can name the place it means.
 */
class GeographicScopeTest extends TestCase
{
    use BuildsScopeFixtures, RefreshDatabase;

    /**
     * @var array<string, \Illuminate\Database\Eloquent\Model>
     */
    private array $world = [];

    /**
     * One country, two states, and a full branch under each.
     *
     * Built once per test method: the hierarchy is identical every time, so the
     * assertions below can talk about places rather than ids.
     *
     * @return array<string, \Illuminate\Database\Eloquent\Model>
     */
    private function world(): array
    {
        if ($this->world !== []) {
            return $this->world;
        }

        $nigeria = $this->makeCountry('Nigeria', 'NGA');
        $nigerState = $this->makeState($nigeria, 'Niger State');
        $nasarawaState = $this->makeState($nigeria, 'Nasarawa State');

        $nigerLgaA = $this->makeLga($nigerState, 'Chanchaga');
        $nigerLgaB = $this->makeLga($nigerState, 'Bosso');
        $wardA1 = $this->makeWard($nigerLgaA, 'Ward A1');
        $wardB1 = $this->makeWard($nigerLgaB, 'Ward B1');

        $nasarawaLga = $this->makeLga($nasarawaState, 'Lafia');
        $nasarawaWard = $this->makeWard($nasarawaLga, 'Lafia Ward');

        return $this->world = [
            'nigeria' => $nigeria,
            'nigerState' => $nigerState,
            'nasarawaState' => $nasarawaState,
            'nigerLgaA' => $nigerLgaA,
            'nigerLgaB' => $nigerLgaB,
            'wardA1' => $wardA1,
            'wardB1' => $wardB1,
            'nasarawaLga' => $nasarawaLga,
            'nasarawaWard' => $nasarawaWard,
            'communityA1a' => $this->makeCommunity($nigerLgaA, $wardA1, 'Community A1a'),
            'communityA1b' => $this->makeCommunity($nigerLgaA, $wardA1, 'Community A1b'),
            'communityB1a' => $this->makeCommunity($nigerLgaB, $wardB1, 'Community B1a'),
            'communityNasarawa' => $this->makeCommunity($nasarawaLga, $nasarawaWard, 'Community Nasarawa'),
            'settlement' => $this->makeCommunity($nigerLgaA, null, 'Settlement Without Ward'),
        ];
    }

    public function test_an_account_with_no_geographic_scope_is_denied(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        foreach (['nigeria', 'nigerState', 'nigerLgaA', 'wardA1', 'communityA1a'] as $key) {
            $this->assertFalse(
                $user->hasGeographicAccess($world[$key]),
                "An unscoped account must not reach [{$key}]."
            );
        }
    }

    public function test_a_country_scope_covers_every_level_beneath_it(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigeria']);

        $keys = ['nigeria', 'nigerState', 'nasarawaState', 'nigerLgaA', 'wardA1', 'communityA1a', 'nasarawaWard'];

        foreach ($keys as $key) {
            $this->assertTrue(
                $user->hasGeographicAccess($world[$key]),
                "A country scope must cover [{$key}]."
            );
        }
    }

    public function test_a_state_scope_covers_its_own_descendants_only(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerState']);

        foreach (['nigerState', 'nigerLgaA', 'wardA1', 'communityA1a', 'settlement'] as $key) {
            $this->assertTrue(
                $user->hasGeographicAccess($world[$key]),
                "A Niger State scope must cover [{$key}]."
            );
        }
    }

    public function test_a_state_scope_does_not_reach_another_state(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerState']);

        foreach (['nasarawaState', 'nasarawaLga', 'nasarawaWard', 'communityNasarawa'] as $key) {
            $this->assertFalse(
                $user->hasGeographicAccess($world[$key]),
                "A Niger State scope must not reach [{$key}]."
            );
        }
    }

    public function test_scope_does_not_travel_upwards(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerState']);

        // A state scope names a state. Reading the country it belongs to is a
        // different kind of access, and must not be inferred from it.
        $this->assertFalse($user->hasGeographicAccess($world['nigeria']));
    }

    public function test_an_lga_scope_covers_its_wards_and_communities(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerLgaA']);

        foreach (['nigerLgaA', 'wardA1', 'communityA1a', 'communityA1b', 'settlement'] as $key) {
            $this->assertTrue(
                $user->hasGeographicAccess($world[$key]),
                "A Chanchaga scope must cover [{$key}]."
            );
        }
    }

    public function test_an_lga_scope_does_not_reach_a_sibling_lga(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerLgaA']);

        foreach (['nigerLgaB', 'wardB1', 'communityB1a'] as $key) {
            $this->assertFalse(
                $user->hasGeographicAccess($world[$key]),
                "A Chanchaga scope must not reach [{$key}]."
            );
        }
    }

    public function test_a_ward_scope_covers_its_communities_and_nothing_else(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['wardA1']);

        $this->assertTrue($user->hasGeographicAccess($world['wardA1']));
        $this->assertTrue($user->hasGeographicAccess($world['communityA1a']));
        $this->assertTrue($user->hasGeographicAccess($world['communityA1b']));

        // The settlement names no ward, so it is not inside this ward — an
        // incomplete hierarchy narrows access, it never widens it.
        $this->assertFalse($user->hasGeographicAccess($world['settlement']));

        // Upward: the LGA is not covered by one of its wards.
        $this->assertFalse($user->hasGeographicAccess($world['nigerLgaA']));

        // Sideways: another branch of the same LGA is untouched.
        $this->assertFalse($user->hasGeographicAccess($world['wardB1']));
        $this->assertFalse($user->hasGeographicAccess($world['communityB1a']));
    }

    public function test_a_community_scope_covers_only_that_community(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['communityA1a']);

        $this->assertTrue($user->hasGeographicAccess($world['communityA1a']));

        // A sibling settlement under the same ward is a different place.
        $this->assertFalse($user->hasGeographicAccess($world['communityA1b']));
        $this->assertFalse($user->hasGeographicAccess($world['settlement']));
        $this->assertFalse($user->hasGeographicAccess($world['wardA1']));
        $this->assertFalse($user->hasGeographicAccess($world['nigerLgaA']));
    }

    public function test_a_wardless_settlement_resolves_through_its_lga(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerLgaA']);
        $this->assertTrue($user->hasGeographicAccess($world['settlement']));

        // A ward scope does not cover it: the settlement names no ward, so it is
        // not inside that ward however nearby it is.
        $wardUser = User::factory()->create();
        $wardUser->assignGeographicScope($world['wardA1']);

        $this->assertFalse($wardUser->hasGeographicAccess($world['settlement']));
    }

    public function test_a_withdrawn_geographic_assignment_grants_nothing(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerState']);
        $this->assertTrue($user->hasGeographicAccess($world['nigerLgaA']));

        $user->revokeGeographicScope($world['nigerState']);

        $this->assertFalse($user->hasGeographicAccess($world['nigerState']));
        $this->assertFalse($user->hasGeographicAccess($world['nigerLgaA']));

        // Deactivated, not deleted: the record of the grant survives.
        $this->assertDatabaseCount('user_geographic_scopes', 1);
    }

    public function test_an_area_that_has_been_deactivated_is_refused_even_when_scoped(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerState']);

        $world['nigerLgaA']->update(['is_active' => false]);

        // The scope is intact, but the area itself is no longer in use.
        $this->assertFalse($user->hasGeographicAccess($world['nigerLgaA']));
        $this->assertTrue($user->hasGeographicAccess($world['nigerState']));
    }

    public function test_rescoping_to_the_same_area_is_idempotent(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerState']);
        $user->assignGeographicScope($world['nigerState']);
        $user->assignGeographicScope($world['nigerState']->fresh());

        $this->assertDatabaseCount('user_geographic_scopes', 1);
    }

    public function test_rescoping_to_a_withdrawn_area_reactivates_it(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerState']);
        $user->revokeGeographicScope($world['nigerState']);
        $this->assertFalse($user->hasGeographicAccess($world['nigerState']));

        $user->assignGeographicScope($world['nigerState']);

        $this->assertTrue($user->hasGeographicAccess($world['nigerState']));
        $this->assertDatabaseCount('user_geographic_scopes', 1);
    }

    public function test_multiple_geographic_assignments_combine(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['nigerLgaA']);
        $user->assignGeographicScope($world['nasarawaLga']);

        $this->assertTrue($user->hasGeographicAccess($world['wardA1']));
        $this->assertTrue($user->hasGeographicAccess($world['nasarawaWard']));

        // Two branches held does not mean everything: Bosso is neither.
        $this->assertFalse($user->hasGeographicAccess($world['wardB1']));
    }

    public function test_different_accounts_hold_different_scopes(): void
    {
        $world = $this->world();

        $nigerUser = User::factory()->create();
        $nigerUser->assignGeographicScope($world['nigerState']);

        $nasarawaUser = User::factory()->create();
        $nasarawaUser->assignGeographicScope($world['nasarawaState']);

        $this->assertTrue($nigerUser->hasGeographicAccess($world['wardA1']));
        $this->assertFalse($nigerUser->hasGeographicAccess($world['nasarawaWard']));

        $this->assertTrue($nasarawaUser->hasGeographicAccess($world['nasarawaWard']));
        $this->assertFalse($nasarawaUser->hasGeographicAccess($world['wardA1']));
    }

    public function test_a_model_outside_the_hierarchy_is_never_covered(): void
    {
        $user = User::factory()->create();
        $user->assignGeographicScope($this->world()['nigeria']);

        // A programme is not a place. Handing one to a geographic check must
        // produce a denial, not a crash and certainly not a match.
        $this->assertFalse($user->hasGeographicAccess($this->makeProgramme()));
    }

    public function test_an_assignment_records_the_level_of_the_area_it_names(): void
    {
        $user = User::factory()->create();
        $world = $this->world();

        $user->assignGeographicScope($world['wardA1']);

        $scope = $user->geographicScopes()->sole();

        $this->assertSame(GeographicScopeLevel::Ward, $scope->level);
        $this->assertSame((int) $world['wardA1']->getKey(), $scope->scopeId());

        // Exactly one level column is populated.
        $this->assertNotNull($scope->ward_id);
        $this->assertNull($scope->country_id);
        $this->assertNull($scope->state_id);
        $this->assertNull($scope->local_government_area_id);
        $this->assertNull($scope->community_id);
    }

    public function test_geographic_scope_never_grants_a_permission(): void
    {
        $user = User::factory()->create();
        $user->assignGeographicScope($this->world()['nigeria']);

        $this->assertSame([], $user->permissionCodes()->all());
        $this->assertFalse($user->hasPermission('beneficiaries.view'));
    }
}

