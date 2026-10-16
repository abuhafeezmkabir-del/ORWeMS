<?php

namespace Tests\Feature\Health;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Health\Concerns\BuildsHealthFixtures;
use Tests\TestCase;

/**
 * Phase 6B — health records follow the child's scope, in both dimensions.
 *
 * Health scope is deliberately UNREMARKABLE: the same
 * ScopeAuthorization::allowsBeneficiary check the HealthRecordPolicy applies,
 * requiring the operator to hold BOTH the child's programme AND its
 * geography. What makes health different is what a failure protects — the
 * most sensitive content ORWeMS holds — so these tests mirror
 * EducationScopeTest exactly, proving the dimensions are conjunctive here
 * too and that a sibling's row under this child's URL 404s rather than
 * leaks.
 */
class HealthScopeTest extends TestCase
{
    use BuildsHealthFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHealthFixtures();
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

        $this->actingAs($officer)->get(route('beneficiaries.health.index', $record))
            ->assertForbidden();
        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload()
        )->assertForbidden();
    }

    public function test_a_geography_scoped_operator_cannot_reach_another_programme(): void
    {
        $officer = $this->geographyOnlyOperator();
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.health.index', $record))
            ->assertForbidden();
        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload()
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

        $this->actingAs($wrongPlace)->get(route('beneficiaries.health.index', $record))
            ->assertForbidden();
        $this->actingAs($wrongProgramme)->get(route('beneficiaries.health.index', $record))
            ->assertForbidden();
        $this->actingAs($right)->get(route('beneficiaries.health.index', $record))
            ->assertOk();
    }

    public function test_record_level_isolation_between_two_in_scope_children(): void
    {
        // Both children are fully in scope; the health URL still binds the
        // row to its own child — a sibling's row under this child's URL 404s.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $sibling = $this->makeBeneficiary($this->pilot, $this->niger);
        $siblingRecord = $this->makeHealthRecord($sibling);

        $this->actingAs($officer)->get(
            route('beneficiaries.health.show', [$record, $siblingRecord])
        )->assertNotFound();
        $this->actingAs($officer)->post(
            route('beneficiaries.health.close.confirm', [$record, $siblingRecord]),
            ['end_date' => now()->toDateString()]
        )->assertNotFound();

        $this->assertTrue($siblingRecord->fresh()->isOpen());
    }
}
