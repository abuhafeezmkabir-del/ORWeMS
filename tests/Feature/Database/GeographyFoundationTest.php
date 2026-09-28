<?php

namespace Tests\Feature\Database;

use App\Models\Community;
use App\Models\Country;
use App\Models\LocalGovernmentArea;
use App\Models\State;
use App\Models\Ward;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographyFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function nigeria(): Country
    {
        return Country::create(['name' => 'Nigeria', 'iso_code' => 'NGA', 'nationality' => 'Nigerian']);
    }

    public function test_a_country_can_have_states(): void
    {
        $country = $this->nigeria();

        $state = State::create(['country_id' => $country->id, 'name' => 'Niger State']);

        $this->assertCount(1, $country->states);
        $this->assertTrue($state->country->is($country));
        // type_label is a database default, so read it back from the database.
        $this->assertSame('State', $state->fresh()->type_label);
    }

    public function test_a_state_can_have_local_government_areas(): void
    {
        $state = State::create(['country_id' => $this->nigeria()->id, 'name' => 'Nasarawa State']);

        $lga = LocalGovernmentArea::create(['state_id' => $state->id, 'name' => 'Test LGA']);

        $this->assertCount(1, $state->localGovernmentAreas);
        $this->assertSame('LGA', $lga->fresh()->type_label);
        $this->assertTrue($lga->state->is($state));
    }

    public function test_an_lga_can_have_wards_and_a_ward_can_have_communities(): void
    {
        $state = State::create(['country_id' => $this->nigeria()->id, 'name' => 'Federal Capital Territory', 'code' => 'NG-FC']);
        $lga = LocalGovernmentArea::create(['state_id' => $state->id, 'name' => 'Test Area Council']);
        $ward = Ward::create(['local_government_area_id' => $lga->id, 'name' => 'Test Ward']);
        $community = Community::create([
            'ward_id' => $ward->id,
            'local_government_area_id' => $lga->id,
            'name' => 'Test Community',
        ]);

        $this->assertCount(1, $lga->wards);
        $this->assertCount(1, $ward->communities);
        $this->assertCount(1, $lga->communities);
        $this->assertTrue($community->ward->is($ward));
        $this->assertTrue($community->localGovernmentArea->is($lga));
    }

    public function test_geography_division_labels_are_data_not_hard_coded(): void
    {
        $country = $this->nigeria();

        $province = State::create([
            'country_id' => $country->id,
            'name' => 'Example Province',
            'type_label' => 'Province',
        ]);
        $district = LocalGovernmentArea::create([
            'state_id' => $province->id,
            'name' => 'Example District',
            'type_label' => 'District',
        ]);

        $this->assertSame('Province', $province->type_label);
        $this->assertSame('District', $district->type_label);
    }

    public function test_state_names_are_unique_within_a_country(): void
    {
        $country = $this->nigeria();
        State::create(['country_id' => $country->id, 'name' => 'Niger State']);

        $this->expectException(QueryException::class);

        State::create(['country_id' => $country->id, 'name' => 'Niger State']);
    }

    public function test_ward_names_are_unique_within_an_lga(): void
    {
        $state = State::create(['country_id' => $this->nigeria()->id, 'name' => 'Niger State']);
        $lga = LocalGovernmentArea::create(['state_id' => $state->id, 'name' => 'Test LGA']);
        Ward::create(['local_government_area_id' => $lga->id, 'name' => 'Test Ward']);

        $this->expectException(QueryException::class);

        Ward::create(['local_government_area_id' => $lga->id, 'name' => 'Test Ward']);
    }
}
