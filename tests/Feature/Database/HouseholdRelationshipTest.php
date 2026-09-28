<?php

namespace Tests\Feature\Database;

use App\Models\Beneficiary;
use App\Models\Household;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_household_can_have_multiple_members(): void
    {
        $household = Household::factory()->create();

        Person::factory()->count(3)->create(['household_id' => $household->id]);

        $this->assertCount(3, $household->members);
        $this->assertTrue($household->members->first()->household->is($household));
    }

    public function test_a_beneficiary_can_be_associated_with_a_household(): void
    {
        $household = Household::factory()->create();
        $beneficiary = Beneficiary::factory()->create(['household_id' => $household->id]);

        $this->assertTrue($beneficiary->household->is($household));
        $this->assertCount(1, $household->beneficiaries);
    }

    public function test_a_person_relationship_links_a_guardian_to_a_beneficiary(): void
    {
        $household = Household::factory()->create();
        $guardian = Person::factory()->create([
            'household_id' => $household->id,
            'is_guardian' => true,
        ]);
        $beneficiary = Beneficiary::factory()->create(['household_id' => $household->id]);

        $relationshipType = RelationshipType::create(['code' => 'guardian', 'name' => 'Guardian']);

        $relationship = PersonRelationship::create([
            'relationship_type_id' => $relationshipType->id,
            'person_id' => $guardian->id,
            'related_beneficiary_id' => $beneficiary->id,
            'is_primary_guardian' => true,
            'start_date' => now()->toDateString(),
        ]);

        $relationship->refresh();

        $this->assertTrue($relationship->relationshipType->is($relationshipType));
        $this->assertTrue($relationship->person->is($guardian));
        $this->assertTrue($relationship->relatedBeneficiary->is($beneficiary));
        $this->assertTrue($relationship->is_primary_guardian);
        $this->assertCount(1, $guardian->relationships);
    }

    public function test_two_persons_can_be_related_without_duplicating_columns(): void
    {
        $household = Household::factory()->create();
        $siblingA = Person::factory()->create(['household_id' => $household->id]);
        $siblingB = Person::factory()->create(['household_id' => $household->id]);
        $relationshipType = RelationshipType::create(['code' => 'sibling', 'name' => 'Sibling']);

        $relationship = PersonRelationship::create([
            'relationship_type_id' => $relationshipType->id,
            'person_id' => $siblingA->id,
            'related_person_id' => $siblingB->id,
        ]);

        $this->assertTrue($relationship->relatedPerson->is($siblingB));
        $this->assertNull($relationship->relatedBeneficiary);
    }

    public function test_relationship_records_enforce_referential_integrity(): void
    {
        $this->expectException(QueryException::class);

        PersonRelationship::create([
            'relationship_type_id' => 999999,
            'person_id' => 999999,
        ]);
    }

    public function test_relationship_types_in_use_cannot_be_deleted(): void
    {
        $guardian = Person::factory()->create();
        $beneficiary = Beneficiary::factory()->create();
        $relationshipType = RelationshipType::create(['code' => 'caregiver', 'name' => 'Caregiver']);

        PersonRelationship::create([
            'relationship_type_id' => $relationshipType->id,
            'person_id' => $guardian->id,
            'related_beneficiary_id' => $beneficiary->id,
        ]);

        $this->expectException(QueryException::class);

        $relationshipType->delete();
    }

    public function test_households_are_soft_deleted_not_destroyed(): void
    {
        $household = Household::factory()->create();

        $household->delete();

        $this->assertSoftDeleted('households', ['id' => $household->id]);
        $this->assertSame(0, Household::count());
    }
}
