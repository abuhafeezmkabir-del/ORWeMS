<?php

namespace Tests\Feature\Health\Concerns;

use App\Models\Beneficiary;
use App\Models\HealthConcern;
use App\Models\HealthRecord;
use Database\Seeders\HealthConcernSeeder;
use Tests\Feature\Beneficiaries\Concerns\BuildsBeneficiaryFixtures;

/**
 * Phase 6B health fixtures.
 *
 * Extends the Phase 4 two-branch world (Niger vs Nasarawa, pilot vs second
 * programme) with the health vocabulary: the concern catalogue and the
 * payload/row helpers every Phase 6B test repeats. Health RECORDS are case
 * data — created per test, never seeded.
 */
trait BuildsHealthFixtures
{
    use BuildsBeneficiaryFixtures;

    protected HealthConcern $wound;

    protected HealthConcern $referral;

    protected function setUpHealthFixtures(): void
    {
        $this->setUpFixtures();
        $this->seed([HealthConcernSeeder::class]);

        $this->wound = HealthConcern::where('code', 'injury_or_wound')->sole();
        $this->referral = HealthConcern::where('code', 'referral_follow_up')->sole();
    }

    /**
     * A valid new-record payload for this officer's world.
     *
     * @return array<string, mixed>
     */
    protected function healthRecordPayload(array $overrides = []): array
    {
        return array_merge([
            'health_concern_id' => $this->wound->getKey(),
            'summary' => 'Wound being followed up until healed.',
            'record_date' => now()->toDateString(),
            'follow_up_status' => 'pending',
            'referral_to' => null,
            'notes' => null,
        ], $overrides);
    }

    /**
     * Insert an OPEN health record directly (bypassing HTTP), for tests
     * that need history to already exist.
     */
    protected function makeHealthRecord(Beneficiary $beneficiary, array $overrides = []): HealthRecord
    {
        return $beneficiary->healthRecords()->create(array_merge([
            'health_concern_id' => $this->wound->getKey(),
            'summary' => 'Wound being followed up until healed.',
            'record_date' => now()->subWeek()->toDateString(),
            'follow_up_status' => 'pending',
        ], $overrides));
    }

    /**
     * Close a health record directly (model path).
     */
    protected function closeHealthRecordDirectly(HealthRecord $record, ?string $end = null): HealthRecord
    {
        $record->end_date = $end ?? now()->toDateString();
        $record->closure_reason = 'Healed';
        $record->save();

        return $record->fresh();
    }
}
