<?php

namespace Tests\Feature\Database;

use App\Models\Beneficiary;
use App\Models\Household;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 2.1 — a person_relationships row must reference exactly one related
 * party: a related person OR a related beneficiary, never both, never neither.
 */
class PersonRelationshipIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function relationshipType(): RelationshipType
    {
        return RelationshipType::create(['code' => 'rel-'.uniqid(), 'name' => 'Guardian']);
    }

    public function test_a_person_can_be_related_to_another_person(): void
    {
        $household = Household::factory()->create();
        $adult = Person::factory()->create(['household_id' => $household->id]);
        $sibling = Person::factory()->create(['household_id' => $household->id]);

        $relationship = PersonRelationship::create([
            'relationship_type_id' => $this->relationshipType()->id,
            'person_id' => $adult->id,
            'related_person_id' => $sibling->id,
        ]);

        $relationship->refresh();

        $this->assertTrue($relationship->relatedPerson->is($sibling));
        $this->assertNull($relationship->relatedBeneficiary);
    }

    public function test_a_person_can_be_related_to_a_beneficiary(): void
    {
        $household = Household::factory()->create();
        $guardian = Person::factory()->create(['household_id' => $household->id]);
        $beneficiary = Beneficiary::factory()->create(['household_id' => $household->id]);

        $relationship = PersonRelationship::create([
            'relationship_type_id' => $this->relationshipType()->id,
            'person_id' => $guardian->id,
            'related_beneficiary_id' => $beneficiary->id,
            'is_primary_guardian' => true,
        ]);

        $relationship->refresh();

        $this->assertTrue($relationship->relatedBeneficiary->is($beneficiary));
        $this->assertNull($relationship->relatedPerson);
    }

    public function test_a_relationship_cannot_reference_both_a_person_and_a_beneficiary(): void
    {
        $household = Household::factory()->create();
        $adult = Person::factory()->create(['household_id' => $household->id]);
        $otherPerson = Person::factory()->create(['household_id' => $household->id]);
        $beneficiary = Beneficiary::factory()->create(['household_id' => $household->id]);

        $this->expectException(ValidationException::class);

        PersonRelationship::create([
            'relationship_type_id' => $this->relationshipType()->id,
            'person_id' => $adult->id,
            'related_person_id' => $otherPerson->id,
            'related_beneficiary_id' => $beneficiary->id,
        ]);
    }

    public function test_a_relationship_must_reference_a_related_party(): void
    {
        $adult = Person::factory()->create();

        $this->expectException(ValidationException::class);

        PersonRelationship::create([
            'relationship_type_id' => $this->relationshipType()->id,
            'person_id' => $adult->id,
            'related_person_id' => null,
            'related_beneficiary_id' => null,
        ]);
    }

    public function test_a_person_cannot_be_related_to_themselves(): void
    {
        $person = Person::factory()->create();

        $this->expectException(ValidationException::class);

        PersonRelationship::create([
            'relationship_type_id' => $this->relationshipType()->id,
            'person_id' => $person->id,
            'related_person_id' => $person->id,
        ]);
    }

    public function test_an_existing_relationship_cannot_be_edited_into_an_invalid_state(): void
    {
        $household = Household::factory()->create();
        $adult = Person::factory()->create(['household_id' => $household->id]);
        $beneficiary = Beneficiary::factory()->create(['household_id' => $household->id]);

        $relationship = PersonRelationship::create([
            'relationship_type_id' => $this->relationshipType()->id,
            'person_id' => $adult->id,
            'related_beneficiary_id' => $beneficiary->id,
        ]);

        $relationship->related_beneficiary_id = null;

        $this->expectException(ValidationException::class);

        $relationship->save();
    }

    public function test_the_database_itself_rejects_a_relationship_without_a_related_party(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'The CHECK constraint is added on MySQL only. SQLite cannot add a '
                .'CHECK to an existing table, so the equivalent model guard is tested above.'
            );
        }

        $type = $this->relationshipType();
        $person = Person::factory()->create();

        $this->expectException(QueryException::class);

        // Bypasses Eloquent entirely, proving the engine enforces the rule.
        DB::table('person_relationships')->insert([
            'relationship_type_id' => $type->id,
            'person_id' => $person->id,
            'related_person_id' => null,
            'related_beneficiary_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_database_itself_rejects_a_relationship_with_two_related_parties(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only CHECK constraint; see the model-guard tests above.');
        }

        $household = Household::factory()->create();
        $type = $this->relationshipType();
        $adult = Person::factory()->create(['household_id' => $household->id]);
        $otherPerson = Person::factory()->create(['household_id' => $household->id]);
        $beneficiary = Beneficiary::factory()->create(['household_id' => $household->id]);

        $this->expectException(QueryException::class);

        DB::table('person_relationships')->insert([
            'relationship_type_id' => $type->id,
            'person_id' => $adult->id,
            'related_person_id' => $otherPerson->id,
            'related_beneficiary_id' => $beneficiary->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
