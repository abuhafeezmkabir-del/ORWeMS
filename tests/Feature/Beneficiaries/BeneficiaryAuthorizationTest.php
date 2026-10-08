<?php

namespace Tests\Feature\Beneficiaries;

use App\Models\Beneficiary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Beneficiaries\Concerns\BuildsBeneficiaryFixtures;
use Tests\TestCase;

/**
 * Phase 4 — who may reach the beneficiary module at all.
 *
 * Guests are redirected to sign in; authenticated operators without the
 * capability receive 403; disabled accounts lose access even with the role;
 * and — the Phase 3C regression — permission WITHOUT scope is still denied.
 */
class BeneficiaryAuthorizationTest extends TestCase
{
    use BuildsBeneficiaryFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    public function test_a_guest_is_redirected_to_sign_in(): void
    {
        $this->get(route('beneficiaries.index'))->assertRedirect(route('login'));
        $this->get(route('beneficiaries.create'))->assertRedirect(route('login'));
    }

    public function test_an_operator_without_the_capability_receives_403(): void
    {
        // The auditor CAN view but cannot register: view passes, create fails.
        $auditor = $this->scopedOperator('auditor', $this->pilot, $this->niger);

        $this->actingAs($auditor)->get(route('beneficiaries.create'))->assertForbidden();
        $this->actingAs($auditor)->post(route('beneficiaries.store'), $this->validPayload($this->pilot, $this->niger))
            ->assertForbidden();
    }

    public function test_a_scoped_case_officer_reaches_index_and_create(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.index'))->assertOk();
        $this->actingAs($officer)->get(route('beneficiaries.create'))->assertOk();
    }

    public function test_a_disabled_operator_is_refused(): void
    {
        // The policy short-circuits before capability checks when the user is
        // disabled, so the request dies at middleware — redirected, never 200.
        // `status` is deliberately NOT mass-assignable (User::$fillable), so
        // update() would silently no-op and leave the account active —
        // forceFill + save is how every other test in this suite disables an
        // account, and it mirrors how administrative code does it.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $officer->forceFill(['status' => \App\Enums\UserStatus::Disabled])->save();
        $this->app['auth']->forgetGuards();

        $this->actingAs($officer)->get(route('beneficiaries.index'))->assertRedirect();
    }

    public function test_permission_without_scope_is_denied_on_detail(): void
    {
        // Holds beneficiaries.view but is scoped to Nasarawa; the record is
        // in Niger. Scope narrows, never grants.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->nasarawa);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.show', $record))->assertForbidden();
    }

    public function test_scope_without_permission_is_denied(): void
    {
        // Scoped to the right place and programme but holds no beneficiary
        // capability at all (system_administrator deliberately has none).
        $admin = $this->scopedOperator('system_administrator', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($admin)->get(route('beneficiaries.show', $record))->assertForbidden();
    }

    public function test_wrong_programme_is_denied(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->second, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.show', $record))->assertForbidden();
    }

    public function test_wrong_geography_is_denied(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->nasarawa);

        $this->actingAs($officer)->get(route('beneficiaries.show', $record))->assertForbidden();
    }

    public function test_correct_permission_programme_and_geography_is_allowed(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.show', $record))->assertOk();
    }

    public function test_the_listing_contains_only_in_scope_records(): void
    {
        $nigerOfficer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);

        $inScope = $this->makeBeneficiary($this->pilot, $this->niger, ['last_name' => 'InScope']);
        $wrongPlace = $this->makeBeneficiary($this->pilot, $this->nasarawa, ['last_name' => 'WrongPlace']);
        $wrongProgramme = $this->makeBeneficiary($this->second, $this->niger, ['last_name' => 'WrongProgramme']);

        $response = $this->actingAs($nigerOfficer)->get(route('beneficiaries.index'));
        $response->assertOk();
        $response->assertSee($inScope->beneficiary_ref);
        $response->assertDontSee($wrongPlace->beneficiary_ref);
        $response->assertDontSee($wrongProgramme->beneficiary_ref);

        // And at the query layer, not just in the rendered HTML.
        $this->assertTrue(
            Beneficiary::query()->accessibleTo($nigerOfficer)->whereKey($inScope->getKey())->exists()
        );
        $this->assertFalse(
            Beneficiary::query()->accessibleTo($nigerOfficer)->whereKey($wrongPlace->getKey())->exists()
        );
        $this->assertFalse(
            Beneficiary::query()->accessibleTo($nigerOfficer)->whereKey($wrongProgramme->getKey())->exists()
        );
    }
}
