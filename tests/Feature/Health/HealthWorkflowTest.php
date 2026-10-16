<?php

namespace Tests\Feature\Health;

use App\Models\HealthRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Health\Concerns\BuildsHealthFixtures;
use Tests\TestCase;

/**
 * Phase 6B — the health record lifecycle: create, amend, close, preserve.
 *
 * Health history is evidence: closing is a dated, reasoned state change and
 * the row remains. Closed records refuse amendment and refuse a second
 * close; updates cannot smuggle closure fields; and the no-exact-open-
 * duplicate rule binds creations while different concerns stay openable.
 */
class HealthWorkflowTest extends TestCase
{
    use BuildsHealthFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHealthFixtures();
    }

    public function test_valid_creation_succeeds(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload()
        )->assertRedirect();

        $this->assertSame(1, HealthRecord::count());
        $this->assertTrue(HealthRecord::sole()->isOpen());
    }

    public function test_a_future_record_date_is_rejected(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload(['record_date' => now()->addDay()->toDateString()])
        )->assertSessionHasErrors('record_date');

        $this->assertSame(0, HealthRecord::count());
    }

    public function test_a_withdrawn_concern_is_rejected(): void
    {
        // Withdrawn concerns keep their history but can no longer be chosen.
        $this->wound->update(['is_active' => false]);

        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload()
        )->assertSessionHasErrors('health_concern_id');

        $this->assertSame(0, HealthRecord::count());
    }

    public function test_an_exact_open_duplicate_is_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $this->makeHealthRecord($record);

        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload()
        )->assertSessionHasErrors('health_concern_id');

        $this->assertSame(1, HealthRecord::count());
    }

    public function test_different_concerns_may_both_be_open(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $this->makeHealthRecord($record);

        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload(['health_concern_id' => $this->referral->getKey()])
        )->assertRedirect();

        $this->assertSame(2, HealthRecord::count());
    }

    public function test_closing_preserves_the_record_and_is_audited(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $healthRecord = $this->makeHealthRecord($record);

        $this->actingAs($officer)->post(
            route('beneficiaries.health.close.confirm', [$record, $healthRecord]),
            ['end_date' => now()->toDateString(), 'closure_reason' => 'Healed']
        )->assertRedirect(route('beneficiaries.show', $record));

        $this->assertFalse($healthRecord->fresh()->isOpen());
        $this->assertDatabaseHas('health_records', ['id' => $healthRecord->getKey()]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'health_record_closed',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_a_closed_record_refuses_amendment_and_second_close(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $healthRecord = $this->makeHealthRecord($record);
        $this->closeHealthRecordDirectly($healthRecord);

        $this->actingAs($officer)->put(
            route('beneficiaries.health.update', [$record, $healthRecord]),
            $this->healthRecordPayload(['summary' => 'Rewritten history.'])
        )->assertForbidden();

        $this->actingAs($officer)->post(
            route('beneficiaries.health.close.confirm', [$record, $healthRecord]),
            ['end_date' => now()->toDateString()]
        )->assertForbidden();
    }
}
