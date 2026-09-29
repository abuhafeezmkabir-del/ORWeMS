<?php

namespace Tests\Feature\Database;

use App\Models\Beneficiary;
use App\Models\Community;
use App\Models\Country;
use App\Models\LocalGovernmentArea;
use App\Models\State;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 2.1 — cross-level geographic consistency.
 *
 * Foreign keys prove that every level exists. These tests cover the rules a
 * foreign key cannot express: that the levels stored side by side on
 * communities and beneficiaries belong to the same branch of the hierarchy.
 */
class GeographicIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private ?Country $country = null;

    private function country(): Country
    {
        return $this->country ??= Country::create(['name' => 'Nigeria', 'iso_code' => 'NGA']);
    }

    /**
     * @return array{0: State, 1: LocalGovernmentArea, 2: Ward, 3: Community}
     */
    private function branch(string $stateName): array
    {
        $state = State::create(['country_id' => $this->country()->id, 'name' => $stateName]);
        $lga = LocalGovernmentArea::create(['state_id' => $state->id, 'name' => $stateName.' LGA']);
        $ward = Ward::create(['local_government_area_id' => $lga->id, 'name' => $stateName.' Ward']);
        $community = Community::create([
            'ward_id' => $ward->id,
            'local_government_area_id' => $lga->id,
            'name' => $stateName.' Community',
        ]);

        return [$state, $lga, $ward, $community];
    }

    public function test_a_community_cannot_claim_a_ward_from_another_lga(): void
    {
        [, $lgaA] = $this->branch('Niger State');
        [, , $wardB] = $this->branch('Nasarawa State');

        $this->expectException(ValidationException::class);

        Community::create([
            'ward_id' => $wardB->id,
            'local_government_area_id' => $lgaA->id,
            'name' => 'Contradictory Community',
        ]);
    }

    public function test_a_community_without_a_ward_is_allowed(): void
    {
        [, $lga] = $this->branch('Niger State');

        $community = Community::create([
            'ward_id' => null,
            'local_government_area_id' => $lga->id,
            'name' => 'Settlement Without Ward',
        ]);

        $this->assertNull($community->ward);
        $this->assertTrue($community->localGovernmentArea->is($lga));
    }

    public function test_a_beneficiary_cannot_mix_an_lga_from_a_different_state(): void
    {
        [$stateA] = $this->branch('Niger State');
        [, $lgaB] = $this->branch('Nasarawa State');

        $this->expectException(ValidationException::class);

        Beneficiary::factory()->create([
            'country_id' => $this->country()->id,
            'state_id' => $stateA->id,
            'local_government_area_id' => $lgaB->id,
        ]);
    }

    public function test_a_beneficiary_ward_must_belong_to_the_selected_lga(): void
    {
        [, $lgaA, , $communityA] = $this->branch('Niger State');
        [, , $wardB] = $this->branch('Nasarawa State');

        $this->expectException(ValidationException::class);

        Beneficiary::factory()->create([
            'local_government_area_id' => $lgaA->id,
            'ward_id' => $wardB->id,
            'community_id' => $communityA->id,
        ]);
    }

    public function test_a_beneficiary_community_must_belong_to_the_selected_ward(): void
    {
        [$state, $lga, $wardA, $communityB] = $this->branch('Niger State');
        [, , $wardB] = $this->branch('Nasarawa State');

        $this->expectException(ValidationException::class);

        Beneficiary::factory()->create([
            'country_id' => $this->country()->id,
            'state_id' => $state->id,
            'local_government_area_id' => $lga->id,
            'ward_id' => $wardB->id,
            'community_id' => $communityB->id,
        ]);
    }

    public function test_a_state_must_belong_to_the_selected_country(): void
    {
        $kenya = Country::create(['name' => 'Kenya', 'iso_code' => 'KEN']);
        [$state] = $this->branch('Niger State');

        $this->expectException(ValidationException::class);

        Beneficiary::factory()->create([
            'country_id' => $kenya->id,
            'state_id' => $state->id,
        ]);
    }

    public function test_a_consistent_full_address_is_accepted(): void
    {
        [$state, $lga, $ward, $community] = $this->branch('Federal Capital Territory');

        $beneficiary = Beneficiary::factory()->create([
            'country_id' => $this->country()->id,
            'state_id' => $state->id,
            'local_government_area_id' => $lga->id,
            'ward_id' => $ward->id,
            'community_id' => $community->id,
        ]);

        $beneficiary->refresh();

        $this->assertTrue($beneficiary->community->is($community));
        $this->assertTrue($beneficiary->ward->is($ward));
        $this->assertTrue($beneficiary->localGovernmentArea->is($lga));
        $this->assertTrue($beneficiary->state->is($state));
        $this->assertTrue($beneficiary->country->is($this->country()));
    }

    public function test_a_partially_recorded_address_is_allowed(): void
    {
        [$state] = $this->branch('Niger State');

        // Registration may capture the state before the household address is
        // verified, so missing levels are allowed — only contradictions are not.
        $beneficiary = Beneficiary::factory()->create(['state_id' => $state->id]);

        $this->assertNull($beneficiary->local_government_area_id);
        $this->assertNull($beneficiary->ward_id);
        $this->assertNull($beneficiary->community_id);
        $this->assertTrue($beneficiary->state->is($state));
    }
}
