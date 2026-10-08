<?php

namespace Tests\Feature\Beneficiaries;

use App\Enums\BeneficiaryStatus;
use App\Models\Beneficiary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Beneficiaries\Concerns\BuildsBeneficiaryFixtures;
use Tests\TestCase;

/**
 * Phase 4 — registration and amendment.
 *
 * Every test uses synthetic fixtures only ("Test Child" style names).
 */
class BeneficiaryCrudTest extends TestCase
{
    use BuildsBeneficiaryFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    public function test_a_scoped_officer_can_register(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);

        $response = $this->actingAs($officer)->post(
            route('beneficiaries.store'),
            $this->validPayload($this->pilot, $this->niger)
        );

        $record = Beneficiary::firstOrFail();
        $response->assertRedirect(route('beneficiaries.show', $record));

        // Reference is server-generated and stable, status starts Registered.
        $this->assertMatchesRegularExpression('/^WICHOV-\d{6}$/', $record->beneficiary_ref);
        $this->assertSame(BeneficiaryStatus::Registered, $record->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'beneficiary_created',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_registration_validates_required_fields(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);

        $this->actingAs($officer)->post(route('beneficiaries.store'), [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'sex', 'programme_id', 'registration_date']);

        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_registration_outside_scope_is_refused_with_field_errors(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);

        // Right programme, wrong state.
        $this->actingAs($officer)->post(
            route('beneficiaries.store'),
            $this->validPayload($this->pilot, $this->nasarawa)
        )->assertSessionHasErrors(['state_id']);

        // Right state, wrong programme.
        $this->actingAs($officer)->post(
            route('beneficiaries.store'),
            $this->validPayload($this->second, $this->niger)
        )->assertSessionHasErrors(['programme_id']);

        $this->assertDatabaseCount('beneficiaries', 0);
    }

    public function test_a_scoped_officer_can_amend(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $payload = $this->validPayload($this->pilot, $this->niger);
        $payload['middle_name'] = 'Amended';

        $this->actingAs($officer)->put(route('beneficiaries.update', $record), $payload)
            ->assertRedirect(route('beneficiaries.show', $record));

        $this->assertSame('Amended', $record->fresh()->middle_name);
        $this->assertDatabaseHas('audit_logs', ['event' => 'beneficiary_updated']);
    }

    public function test_status_cannot_be_forged_through_update(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $payload = $this->validPayload($this->pilot, $this->niger);
        $payload['status'] = 'active';

        $this->actingAs($officer)->put(route('beneficiaries.update', $record), $payload);

        // Still Registered: lifecycle moves happen only via verify().
        $this->assertSame(BeneficiaryStatus::Registered, $record->fresh()->status);
    }

    public function test_contradictory_geography_is_rejected(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        // Niger state paired with a Nasarawa LGA: both rows exist, but they
        // describe different branches of the hierarchy.
        $nasarawaLga = \App\Models\LocalGovernmentArea::create([
            'state_id' => $this->nasarawa->getKey(), 'name' => 'Foreign LGA',
        ]);

        $payload = $this->validPayload($this->pilot, $this->niger);
        $payload['local_government_area_id'] = $nasarawaLga->getKey();

        $this->actingAs($officer)->put(route('beneficiaries.update', $record), $payload)
            ->assertSessionHasErrors(['state_id']);
    }

    public function test_likely_duplicates_are_blocked(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $this->makeBeneficiary($this->pilot, $this->niger, [
            'first_name' => 'Test',
            'last_name' => 'Child',
            'sex' => 'female',
            'date_of_birth' => '2015-03-10',
        ]);

        $payload = $this->validPayload($this->pilot, $this->niger);
        $payload['date_of_birth'] = '2015-03-10';

        $this->actingAs($officer)->post(route('beneficiaries.store'), $payload)
            ->assertSessionHasErrors(['first_name']);

        $this->assertDatabaseCount('beneficiaries', 1);
    }

    public function test_same_name_with_different_identity_is_allowed(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $this->makeBeneficiary($this->pilot, $this->niger, [
            'first_name' => 'Test',
            'last_name' => 'Child',
            'sex' => 'female',
            'date_of_birth' => '2015-03-10',
        ]);

        // Same names, different sex and DOB: a different child, not a duplicate.
        $payload = $this->validPayload($this->pilot, $this->niger);
        $payload['sex'] = 'male';
        $payload['date_of_birth'] = '2012-07-22';

        $this->actingAs($officer)->post(route('beneficiaries.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('beneficiaries', 2);
    }
}
