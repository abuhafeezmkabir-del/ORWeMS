<?php

namespace Tests\Feature\Database;

use App\Models\Beneficiary;
use App\Models\Country;
use App\Models\Household;
use App\Models\InterventionType;
use App\Models\NeedCategory;
use App\Models\Programme;
use App\Models\RelationshipType;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_is_idempotent_for_reference_data(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, Country::count());
        $this->assertSame(3, State::count());
        $this->assertSame(1, Programme::count());
        $this->assertSame(12, RelationshipType::count());
        $this->assertSame(10, NeedCategory::count());
        $this->assertSame(11, InterventionType::count());
    }

    public function test_pilot_geography_is_seeded_and_no_invented_subdivisions_exist(): void
    {
        $this->seed();

        $nigeria = Country::where('iso_code', 'NGA')->sole();

        $this->assertSame('Nigeria', $nigeria->name);
        $this->assertSame(
            ['Federal Capital Territory', 'Nasarawa State', 'Niger State'],
            State::where('country_id', $nigeria->id)->orderBy('name')->pluck('name')->all()
        );

        // LGAs, wards and communities must come from verified project data.
        $this->assertSame(0, $nigeria->states->sum(fn ($state) => $state->localGovernmentAreas->count()));
        $this->assertDatabaseCount('wards', 0);
        $this->assertDatabaseCount('communities', 0);
    }

    public function test_pilot_programme_is_seeded_without_duplication(): void
    {
        $this->seed();

        $programme = Programme::where('code', 'WICHOV-PILOT')->sole();

        $this->assertSame('WICHOV Pilot Programme', $programme->name);
        $this->assertSame('active', $programme->status->value);
    }

    public function test_seeding_does_not_fabricate_humanitarian_records(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(0, Beneficiary::count());
        $this->assertSame(0, Household::count());
        $this->assertDatabaseCount('assessments', 0);
        $this->assertDatabaseCount('needs', 0);
        $this->assertDatabaseCount('interventions', 0);
        $this->assertDatabaseCount('persons', 0);
        $this->assertDatabaseCount('person_relationships', 0);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_additional_states_can_be_added_as_data_without_schema_change(): void
    {
        $this->seed();

        $nigeria = Country::where('iso_code', 'NGA')->sole();

        State::create(['country_id' => $nigeria->id, 'name' => 'Kano State', 'code' => 'NG-KN']);

        $this->assertSame(4, State::count());
        $this->assertDatabaseHas('states', ['name' => 'Kano State']);
    }
}
