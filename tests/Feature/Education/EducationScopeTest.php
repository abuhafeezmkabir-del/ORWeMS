<?php

namespace Tests\Feature\Education;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Education\Concerns\BuildsEducationFixtures;
use Tests\TestCase;

/**
 * Phase 6A — education history follows the child's scope, in both dimensions.
 *
 * The policy checks ScopeAuthorization::allowsBeneficiary, which requires the
 * operator to hold BOTH the beneficiary's programme AND its geography. These
 * tests prove each half independently: right programme in the wrong place is
 * refused, right place under the wrong programme is refused, and a record
 * under a sibling child is unreachable even when everything else matches.
 */
class EducationScopeTest extends TestCase
{
    use BuildsEducationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEducationFixtures();
    }

    private function programmeOnlyOperator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'case_officer')->sole());
        $user->assignProgrammeScope($this->pilot);

        return $user;
    }

    private function geographyOnlyOperator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', 'case_officer')->sole());
        $user->assignGeographicScope($this->niger);

        return $user;
    }

    public function test_a_programme_scoped_operator_cannot_reach_another_state(): void
    {
        $officer = $this->programmeOnlyOperator();
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.education.index', $record))
            ->assertForbidden();
        $this->actingAs($officer)->post(
            route('beneficiaries.education.store', $record),
            $this->enrolmentPayload()
        )->assertForbidden();
    }

    public function test_a_geography_scoped_operator_cannot_reach_another_programme(): void
    {
        $officer = $this->geographyOnlyOperator();
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.education.index', $record))
            ->assertForbidden();
        $this->actingAs($officer)->post(
            route('beneficiaries.education.store', $record),
            $this->enrolmentPayload()
        )->assertForbidden();
    }

    public function test_both_dimensions_together_grant_and_either_alone_refuses(): void
    {
        // Right programme, wrong place.
        $wrongPlace = $this->scopedOperator('case_officer', $this->pilot, $this->nasarawa);
        // Right place, wrong programme.
        $wrongProgramme = $this->scopedOperator('case_officer', $this->second, $this->niger);
        // Both right.
        $right = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($wrongPlace)->get(route('beneficiaries.education.index', $record))
            ->assertForbidden();
        $this->actingAs($wrongProgramme)->get(route('beneficiaries.education.index', $record))
            ->assertForbidden();
        $this->actingAs($right)->get(route('beneficiaries.education.index', $record))
            ->assertOk();
    }

    public function test_record_level_isolation_between_two_in_scope_children(): void
    {
        // Both children are fully in scope; the enrolment URL still binds the
        // row to its own child — a sibling's row under this child's URL 404s.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $sibling = $this->makeBeneficiary($this->pilot, $this->niger);
        $siblingEnrolment = $this->makeEnrolment($sibling);

        $this->actingAs($officer)->get(
            route('beneficiaries.education.close', [$record, $siblingEnrolment])
        )->assertNotFound();
        $this->actingAs($officer)->post(
            route('beneficiaries.education.close.confirm', [$record, $siblingEnrolment]),
            ['end_date' => now()->toDateString()]
        )->assertNotFound();

        $this->assertTrue($siblingEnrolment->fresh()->isCurrent());
    }
}
