<?php

namespace Tests\Feature\Database;

use App\Enums\BeneficiaryStatus;
use App\Enums\Sex;
use App\Models\Beneficiary;
use App\Models\Community;
use App\Models\Country;
use App\Models\LocalGovernmentArea;
use App\Models\State;
use App\Models\Ward;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiaryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_beneficiary_can_be_created_and_receives_a_stable_reference(): void
    {
        $beneficiary = Beneficiary::factory()->create();

        $this->assertNotNull($beneficiary->id);
        $this->assertMatchesRegularExpression('/^WICHOV-\d{6}$/', $beneficiary->beneficiary_ref);
        $this->assertSame('WICHOV-'.str_pad((string) $beneficiary->id, 6, '0', STR_PAD_LEFT), $beneficiary->beneficiary_ref);
        $this->assertSame(BeneficiaryStatus::Registered, $beneficiary->status);
    }

    public function test_beneficiary_references_are_unique_per_record(): void
    {
        $first = Beneficiary::factory()->create();
        $second = Beneficiary::factory()->create();

        $this->assertNotSame($first->beneficiary_ref, $second->beneficiary_ref);
    }

    public function test_a_duplicate_beneficiary_reference_is_rejected_by_the_database(): void
    {
        $first = Beneficiary::factory()->create();

        $this->expectException(QueryException::class);

        Beneficiary::factory()->create(['beneficiary_ref' => $first->beneficiary_ref]);
    }

    public function test_required_biodata_is_enforced_by_the_schema(): void
    {
        $this->expectException(QueryException::class);

        // sex and registration_date are required columns.
        Beneficiary::create([
            'beneficiary_ref' => 'WICHOV-999999',
            'first_name' => 'Fixture',
            'last_name' => 'Subject',
        ]);
    }

    public function test_a_beneficiary_can_be_linked_to_full_geography(): void
    {
        $country = Country::create(['name' => 'Nigeria', 'iso_code' => 'NGA']);
        $state = State::create(['country_id' => $country->id, 'name' => 'Niger State']);
        $lga = LocalGovernmentArea::create(['state_id' => $state->id, 'name' => 'Test LGA']);
        $ward = Ward::create(['local_government_area_id' => $lga->id, 'name' => 'Test Ward']);
        $community = Community::create([
            'ward_id' => $ward->id,
            'local_government_area_id' => $lga->id,
            'name' => 'Test Community',
        ]);

        $beneficiary = Beneficiary::factory()->create([
            'nationality_country_id' => $country->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'local_government_area_id' => $lga->id,
            'ward_id' => $ward->id,
            'community_id' => $community->id,
        ]);

        $beneficiary->refresh();

        $this->assertTrue($beneficiary->country->is($country));
        $this->assertTrue($beneficiary->state->is($state));
        $this->assertTrue($beneficiary->localGovernmentArea->is($lga));
        $this->assertTrue($beneficiary->ward->is($ward));
        $this->assertTrue($beneficiary->community->is($community));
    }

    public function test_sex_and_date_of_birth_accuracy_are_cast_to_enums(): void
    {
        $beneficiary = Beneficiary::factory()->create(['sex' => Sex::Female]);

        $this->assertSame(Sex::Female, $beneficiary->fresh()->sex);
        $this->assertSame('approximate', $beneficiary->fresh()->date_of_birth_accuracy->value);
    }

    public function test_beneficiaries_are_soft_deleted_not_destroyed(): void
    {
        $beneficiary = Beneficiary::factory()->create();

        $beneficiary->delete();

        $this->assertSoftDeleted('beneficiaries', ['id' => $beneficiary->id]);
        $this->assertSame(0, Beneficiary::count());
        $this->assertSame(1, Beneficiary::withTrashed()->count());
    }

    public function test_the_beneficiary_reference_does_not_encode_personal_characteristics(): void
    {
        $beneficiary = Beneficiary::factory()->create([
            'sex' => Sex::Male,
            'date_of_birth' => '2015-04-01',
        ]);

        $this->assertSame(0, preg_match('/male|female|2015/', $beneficiary->beneficiary_ref));
    }
}
