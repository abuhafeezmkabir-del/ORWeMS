<?php

namespace Tests\Feature\Health;

use App\Enums\UserStatus;
use App\Models\HealthRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Health\Concerns\BuildsHealthFixtures;
use Tests\TestCase;

/**
 * Phase 6B — who may work with health history, and through which door.
 *
 * The same two-step chain as Phases 4–6A: route middleware (permission) then
 * policy (permission AND beneficiary scope), plus the health-specific rule
 * that health is its OWN permission family. A beneficiaries.view operator and
 * an education operator are both refused: health is the most sensitive
 * content ORWeMS holds, and least privilege means the default is no access.
 */
class HealthAuthorizationTest extends TestCase
{
    use BuildsHealthFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHealthFixtures();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->get(route('beneficiaries.health.index', $record))
            ->assertRedirect(route('login'));
    }

    public function test_a_disabled_operator_is_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $officer->forceFill(['status' => UserStatus::Disabled])->save();
        $this->app['auth']->forgetGuards();

        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.health.index', $record))
            ->assertRedirect();
    }

    public function test_permission_without_scope_is_denied(): void
    {
        // Holds health_records.view but is scoped to Nasarawa; child is Niger.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->nasarawa);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.health.index', $record))
            ->assertForbidden();
    }

    public function test_beneficiaries_view_alone_does_not_authorize(): void
    {
        // registration_officer holds beneficiaries.view + education.view —
        // but no health_records.* at all.
        $registrar = $this->scopedOperator('registration_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $this->makeHealthRecord($record);

        $this->assertTrue($registrar->hasPermission('education.view'));
        $this->assertFalse($registrar->hasPermission('health_records.view'));

        $this->actingAs($registrar)->get(route('beneficiaries.health.index', $record))
            ->assertForbidden();
        $this->actingAs($registrar)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload()
        )->assertForbidden();

        $this->assertSame(1, HealthRecord::count());

        // And the case file renders the neutral line, not the record.
        $this->actingAs($registrar)->get(route('beneficiaries.show', $record))
            ->assertOk()
            ->assertSee('Health information is restricted.', false)
            ->assertDontSee('Wound being followed', false);
    }

    public function test_a_case_officer_may_record_and_it_is_audited(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.health.create', $record))
            ->assertOk();
        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload()
        )->assertRedirect(route('beneficiaries.health.show', [$record, HealthRecord::sole()]));

        $this->assertTrue(HealthRecord::sole()->isOpen());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'health_record_created',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_a_manager_may_view_and_close_but_not_create(): void
    {
        $manager = $this->scopedOperator('programme_manager', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $healthRecord = $this->makeHealthRecord($record);

        $this->actingAs($manager)->get(route('beneficiaries.health.index', $record))
            ->assertOk();
        $this->actingAs($manager)->get(route('beneficiaries.health.create', $record))
            ->assertForbidden();
        $this->actingAs($manager)->get(
            route('beneficiaries.health.close', [$record, $healthRecord])
        )->assertOk();
    }

    public function test_an_archived_child_gets_no_new_health_record(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $record->delete();

        $this->actingAs($officer)->get(route('beneficiaries.health.create', $record))
            ->assertForbidden();
        $this->assertSame(0, HealthRecord::count());
    }

    public function test_a_record_id_under_another_child_is_a_404(): void
    {
        // A cross-record id is a mistake (or an attempt); 403 would confirm
        // the id exists, so it is answered with 404.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $sibling = $this->makeBeneficiary($this->pilot, $this->niger);
        $healthRecord = $this->makeHealthRecord($sibling);

        $this->actingAs($officer)->get(
            route('beneficiaries.health.edit', [$record, $healthRecord])
        )->assertNotFound();
    }

    public function test_destructive_deletion_is_unavailable(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $healthRecord = $this->makeHealthRecord($record);

        $this->actingAs($officer)->delete(
            '/beneficiaries/'.$record->beneficiary_ref.'/health/'.$healthRecord->getKey()
        )->assertStatus(405);
        $this->assertDatabaseHas('health_records', ['id' => $healthRecord->getKey()]);
    }
}
