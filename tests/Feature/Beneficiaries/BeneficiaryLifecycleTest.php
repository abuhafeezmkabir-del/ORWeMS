<?php

namespace Tests\Feature\Beneficiaries;

use App\Enums\BeneficiaryStatus;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Beneficiaries\Concerns\BuildsBeneficiaryFixtures;
use Tests\TestCase;

/**
 * Phase 4 — verification and archiving.
 *
 * Separation of duties: entering a record never confirms it. Archiving is
 * the terminal action; destructive deletion is asserted unavailable.
 */
class BeneficiaryLifecycleTest extends TestCase
{
    use BuildsBeneficiaryFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    public function test_a_registration_officer_cannot_verify(): void
    {
        $registrar = $this->scopedOperator('registration_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($registrar)->post(route('beneficiaries.verify', $record))->assertForbidden();
        $this->assertSame(BeneficiaryStatus::Registered, $record->fresh()->status);
    }

    public function test_a_manager_can_verify_and_it_is_audited(): void
    {
        $manager = $this->scopedOperator('programme_manager', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($manager)->post(route('beneficiaries.verify', $record))
            ->assertRedirect(route('beneficiaries.show', $record));

        $this->assertSame(BeneficiaryStatus::Active, $record->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'beneficiary_verified',
            'user_id' => $manager->getKey(),
        ]);
    }

    public function test_verification_is_idempotent(): void
    {
        $manager = $this->scopedOperator('programme_manager', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($manager)->post(route('beneficiaries.verify', $record))->assertRedirect();
        $this->actingAs($manager)->post(route('beneficiaries.verify', $record))->assertRedirect();

        // One verification, one audit row — a double-click writes no history.
        $this->assertSame(1, AuditLog::where('event', 'beneficiary_verified')->count());
    }

    public function test_a_manager_can_archive_and_it_is_audited(): void
    {
        $manager = $this->scopedOperator('programme_manager', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($manager)->get(route('beneficiaries.archive', $record))->assertOk();
        $this->actingAs($manager)->post(route('beneficiaries.archive.confirm', $record))
            ->assertRedirect(route('beneficiaries.index'));

        $this->assertSoftDeleted('beneficiaries', ['id' => $record->getKey()]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'beneficiary_archived',
            'user_id' => $manager->getKey(),
        ]);
    }

    public function test_an_archived_record_cannot_be_amended(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $record->delete();

        $this->actingAs($officer)->put(
            route('beneficiaries.update', $record->beneficiary_ref),
            $this->validPayload($this->pilot, $this->niger)
        )->assertForbidden();

        // Archiving needs its own capability: a case officer cannot archive.
        $this->actingAs($officer)->post(
            route('beneficiaries.archive.confirm', $record->beneficiary_ref)
        )->assertForbidden();
    }

    public function test_destructive_deletion_is_unavailable(): void
    {
        $manager = $this->scopedOperator('programme_manager', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        // No DELETE route exists: the framework answers 405, and the row
        // survives regardless.
        $this->actingAs($manager)->delete('/beneficiaries/'.$record->beneficiary_ref)->assertStatus(405);
        $this->assertDatabaseHas('beneficiaries', ['id' => $record->getKey()]);
    }
}
