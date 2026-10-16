<?php

namespace Tests\Feature\Health;

use App\Models\AuditLog;
use App\Models\HealthRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Health\Concerns\BuildsHealthFixtures;
use Tests\TestCase;

/**
 * Phase 6B — what an unauthorized eye must never see, and what the audit
 * trail must never hold.
 *
 * Health is the most sensitive content ORWeMS holds, so the privacy bar is
 * stricter than for education or care: the unauthorized case file answers
 * with a SINGLE neutral line — no counts (a count discloses how much health
 * history exists), no summaries, no referral detail, no follow-up state.
 * And the audit trail carries identifiers only: the summary, referral
 * destination and notes must never enter audit_logs. Forged actor,
 * beneficiary or scope fields are derived server-side and cannot override.
 */
class HealthPrivacyTest extends TestCase
{
    use BuildsHealthFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpHealthFixtures();
    }

    public function test_the_case_file_leaks_no_count_no_summary_no_referral(): void
    {
        $registrar = $this->scopedOperator('registration_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $healthRecord = $this->makeHealthRecord($record, [
            'summary' => 'SENSITIVE_GRAPH_MARKER free care summary',
            'referral_to' => 'SENSITIVE_GRAPH_REFERRAL General Hospital',
        ]);

        $response = $this->actingAs($registrar)->get(route('beneficiaries.show', $record));

        $response->assertOk()
            ->assertSee('Health information is restricted.', false)
            ->assertDontSee('SENSITIVE_GRAPH_MARKER', false)
            ->assertDontSee('SENSITIVE_GRAPH_REFERRAL', false)
            ->assertDontSee('Full health history', false)
            ->assertDontSee('Record health record', false);

        // The count is the subtle leak: the authorized card renders its health
        // rows as list items with Open badges; the neutral card renders
        // neither. Other cards (Record history) render their own list
        // items, so the check is scoped to the Health card's block only.
        $html = $response->getContent();
        $healthStart = strpos($html, 'Health records');
        $this->assertNotFalse($healthStart);
        $healthCardHtml = substr($html, $healthStart, 1200);
        $this->assertStringNotContainsString('<li', $healthCardHtml);
        $this->assertStringNotContainsString('>Open<', $healthCardHtml);
        $this->assertTrue($healthRecord->fresh()->isOpen());
    }

    public function test_audit_metadata_carries_identifiers_only(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            $this->healthRecordPayload([
                'summary' => 'AUDIT_SECRET_MARKER wound narrative',
                'referral_to' => 'AUDIT_SECRET_REFERRAL clinic name',
                'notes' => 'AUDIT_SECRET_NOTE private casework note',
            ])
        )->assertRedirect();

        $healthRecord = HealthRecord::sole();
        $viewed = $this->actingAs($officer)->get(
            route('beneficiaries.health.show', [$record, $healthRecord])
        );
        $viewed->assertOk();

        $this->actingAs($officer)->post(
            route('beneficiaries.health.close.confirm', [$record, $healthRecord]),
            ['end_date' => now()->toDateString(), 'closure_reason' => 'AUDIT_SECRET_CLOSURE detail']
        )->assertRedirect();

        $logs = AuditLog::query()
            ->whereIn('event', ['health_record_created', 'health_record_viewed', 'health_record_closed'])
            ->get();

        $this->assertNotEmpty($logs);

        foreach ($logs as $log) {
            $blob = json_encode($log->context ?? []).' '.$log->event;
            $this->assertStringNotContainsString('AUDIT_SECRET_MARKER', $blob);
            $this->assertStringNotContainsString('AUDIT_SECRET_REFERRAL', $blob);
            $this->assertStringNotContainsString('AUDIT_SECRET_NOTE', $blob);
            $this->assertStringNotContainsString('AUDIT_SECRET_CLOSURE', $blob);
        }

        // …but the identifiers ARE there: the trail still says what happened.
        $created = $logs->firstWhere('event', 'health_record_created');
        $this->assertSame($record->beneficiary_ref, $created->context['beneficiary_ref'] ?? null);
        $this->assertSame($healthRecord->getKey(), $created->context['health_record_id'] ?? null);
        $this->assertSame($officer->getKey(), $created->user_id);
    }

    public function test_forged_actor_beneficiary_and_scope_fields_cannot_override(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $sibling = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.health.store', $record),
            array_merge($this->healthRecordPayload(), [
                'beneficiary_id' => $sibling->getKey(),
                'user_id' => 999001,
                'created_by' => 999001,
                'programme_id' => 999001,
                'state_id' => 999001,
            ])
        )->assertRedirect();

        $stored = HealthRecord::sole();

        // The row belongs to the URL's child; forged keys never left the payload.
        $this->assertSame($record->getKey(), $stored->beneficiary_id);
        $this->assertSame(0, HealthRecord::where('beneficiary_id', $sibling->getKey())->count());

        // The actor is the session operator, not the forged id.
        $created = AuditLog::query()->where('event', 'health_record_created')->sole();
        $this->assertSame($officer->getKey(), $created->user_id);
    }
}
