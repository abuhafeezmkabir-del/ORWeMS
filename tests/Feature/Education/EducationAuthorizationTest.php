<?php

namespace Tests\Feature\Education;

use App\Enums\UserStatus;
use App\Models\EducationEnrolment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Education\Concerns\BuildsEducationFixtures;
use Tests\TestCase;

/**
 * Phase 6A — who may work with education history, and through which door.
 *
 * The same two-step chain as Phases 4–5: route middleware (permission) then
 * policy (permission AND beneficiary scope), plus the education-specific
 * lifecycle abilities. A registration officer sees history but cannot write
 * it; a manager can close but not open; only a case officer runs the loop.
 */
class EducationAuthorizationTest extends TestCase
{
    use BuildsEducationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEducationFixtures();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->get(route('beneficiaries.education.index', $record))
            ->assertRedirect(route('login'));
    }

    public function test_a_disabled_operator_is_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $officer->forceFill(['status' => UserStatus::Disabled])->save();
        $this->app['auth']->forgetGuards();

        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.education.index', $record))
            ->assertRedirect();
    }

    public function test_permission_without_scope_is_denied(): void
    {
        // Holds education.view but is scoped to Nasarawa; the child is Niger.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->nasarawa);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.education.index', $record))
            ->assertForbidden();
    }

    public function test_a_view_only_role_sees_history_but_cannot_write_it(): void
    {
        $registrar = $this->scopedOperator('registration_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($registrar)->get(route('beneficiaries.education.index', $record))
            ->assertOk();
        $this->actingAs($registrar)->get(route('beneficiaries.education.create', $record))
            ->assertForbidden();
        $this->actingAs($registrar)->post(
            route('beneficiaries.education.store', $record),
            $this->enrolmentPayload()
        )->assertForbidden();

        $this->assertSame(0, EducationEnrolment::count());
    }

    public function test_a_case_officer_may_record_and_it_is_audited(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.education.create', $record))
            ->assertOk();
        $this->actingAs($officer)->post(
            route('beneficiaries.education.store', $record),
            $this->enrolmentPayload()
        )->assertRedirect(route('beneficiaries.show', $record));

        $enrolment = EducationEnrolment::sole();
        $this->assertTrue($enrolment->isCurrent());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'education_enrolment_created',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_a_manager_may_view_and_close_but_not_create(): void
    {
        // The manager holds education.view and education.close, not
        // education.create — closing is the consequential capability.
        $manager = $this->scopedOperator('programme_manager', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $enrolment = $this->makeEnrolment($record);

        $this->actingAs($manager)->get(route('beneficiaries.education.index', $record))
            ->assertOk();
        $this->actingAs($manager)->get(route('beneficiaries.education.create', $record))
            ->assertForbidden();
        $this->actingAs($manager)->get(
            route('beneficiaries.education.close', [$record, $enrolment])
        )->assertOk();
    }

    public function test_an_archived_child_gets_no_new_enrolment(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $record->delete();

        $this->actingAs($officer)->get(route('beneficiaries.education.create', $record))
            ->assertForbidden();
        $this->assertSame(0, EducationEnrolment::count());
    }

    public function test_an_enrolment_id_under_another_child_is_a_404(): void
    {
        // A cross-record id is a mistake (or an attempt); 403 would confirm
        // the id exists, so it is answered with 404.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $siblingRecord = $this->makeBeneficiary($this->pilot, $this->niger);
        $enrolment = $this->makeEnrolment($siblingRecord);

        $this->actingAs($officer)->get(
            route('beneficiaries.education.edit', [$record, $enrolment])
        )->assertNotFound();
    }

    public function test_destructive_deletion_is_unavailable(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $enrolment = $this->makeEnrolment($record);

        $this->actingAs($officer)->delete(
            '/beneficiaries/'.$record->beneficiary_ref.'/education/'.$enrolment->getKey()
        )->assertStatus(405);
        $this->assertDatabaseHas('education_enrolments', ['id' => $enrolment->getKey()]);
    }
}
