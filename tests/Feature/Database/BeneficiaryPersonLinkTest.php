<?php

namespace Tests\Feature\Database;

use App\Models\Beneficiary;
use App\Models\Household;
use App\Models\Person;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 2.1 — the beneficiary ↔ household-roster link.
 *
 * `beneficiaries.person_id` is optional and points at the child's own row in
 * `persons` so a single identity is kept. It must reference at most one
 * beneficiary, and when both records name a household they must agree.
 */
class BeneficiaryPersonLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_beneficiary_can_link_to_its_own_household_roster_record(): void
    {
        $household = Household::factory()->create();
        $rosterRecord = Person::factory()->create(['household_id' => $household->id]);

        $beneficiary = Beneficiary::factory()->create([
            'household_id' => $household->id,
            'person_id' => $rosterRecord->id,
        ]);

        $this->assertTrue($beneficiary->person->is($rosterRecord));
        $this->assertTrue($beneficiary->household->is($household));
    }

    public function test_two_beneficiaries_cannot_share_one_roster_record(): void
    {
        $household = Household::factory()->create();
        $rosterRecord = Person::factory()->create(['household_id' => $household->id]);

        Beneficiary::factory()->create([
            'household_id' => $household->id,
            'person_id' => $rosterRecord->id,
        ]);

        $this->expectException(QueryException::class);

        // Uniqueness is enforced by the database, not only by the application.
        Beneficiary::factory()->create([
            'household_id' => $household->id,
            'person_id' => $rosterRecord->id,
        ]);
    }

    public function test_many_beneficiaries_may_have_no_roster_record(): void
    {
        $household = Household::factory()->create();

        Beneficiary::factory()->count(3)->create([
            'household_id' => $household->id,
            'person_id' => null,
        ]);

        $this->assertSame(3, Beneficiary::whereNull('person_id')->count());
    }

    public function test_a_roster_record_from_another_household_is_rejected(): void
    {
        $rosterRecord = Person::factory()->create();
        $otherHousehold = Household::factory()->create();

        $this->expectException(ValidationException::class);

        Beneficiary::factory()->create([
            'household_id' => $otherHousehold->id,
            'person_id' => $rosterRecord->id,
        ]);
    }

    public function test_an_incomplete_roster_record_without_a_household_is_allowed(): void
    {
        // The roster row has not been assigned to a household yet. That is
        // incomplete data, not a contradiction, so it is allowed.
        $rosterRecord = Person::factory()->create(['household_id' => null]);
        $household = Household::factory()->create();

        $beneficiary = Beneficiary::factory()->create([
            'household_id' => $household->id,
            'person_id' => $rosterRecord->id,
        ]);

        $this->assertTrue($beneficiary->person->is($rosterRecord));
    }

    public function test_soft_deleting_the_roster_record_preserves_the_beneficiary_link(): void
    {
        $household = Household::factory()->create();
        $rosterRecord = Person::factory()->create(['household_id' => $household->id]);
        $beneficiary = Beneficiary::factory()->create([
            'household_id' => $household->id,
            'person_id' => $rosterRecord->id,
        ]);

        $rosterRecord->delete();

        $this->assertSoftDeleted('persons', ['id' => $rosterRecord->id]);

        // The beneficiary is untouched and still points at the withdrawn row.
        $beneficiary->refresh();
        $this->assertSame($rosterRecord->id, $beneficiary->person_id);
        $this->assertTrue(Beneficiary::find($beneficiary->id)->exists);
        $this->assertNotNull(Person::withTrashed()->find($rosterRecord->id));
    }
}
