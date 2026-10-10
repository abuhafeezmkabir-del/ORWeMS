<?php

namespace Tests\Feature\Care;

use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Care\Concerns\BuildsCareFixtures;
use Tests\TestCase;

/**
 * Phase 5 — the family/care relationship workflow.
 *
 * Exactly-one-related-party edges, three counterpart modes, the roster
 * capability back-door check, duplicate and self-edge refusals, and ending
 * as a dated close rather than a deletion. The edge record is evidence:
 * it is created and ended, never destroyed.
 */
class RelationshipWorkflowTest extends TestCase
{
    use BuildsCareFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCareFixtures();
    }

    public function test_a_relationship_to_an_existing_person_is_recorded_and_audited(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.relationships.create', $record))
            ->assertOk();
        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian)
        )->assertRedirect(route('beneficiaries.show', $record));

        $edge = PersonRelationship::sole();
        $this->assertSame($this->guardian->getKey(), $edge->person_id);
        $this->assertSame($record->getKey(), $edge->related_beneficiary_id);
        $this->assertNull($edge->end_date);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'beneficiary_relationship_created',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_a_view_only_role_cannot_record_relationships(): void
    {
        $assessor = $this->scopedOperator('assessor', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($assessor)->get(route('beneficiaries.relationships.create', $record))
            ->assertForbidden();
        $this->actingAs($assessor)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian)
        )->assertForbidden();

        $this->assertSame(0, PersonRelationship::count());
    }

    public function test_recording_a_new_person_requires_the_roster_capability_too(): void
    {
        // Recording must not be a back door to growing household rosters:
        // without persons.create the new_person mode is a 403 — a missing
        // capability is not about the input, so it is not a validation error.
        $registrar = $this->scopedOperator('registration_officer', $this->pilot, $this->niger);
        Role::where('code', 'registration_officer')->sole()
            ->permissions()->detach(Permission::where('code', 'persons.create')->sole()->getKey());
        $this->app['auth']->forgetGuards();

        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($registrar)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian, [
                'related_party_type' => 'new_person',
                'related_person_id' => null,
                'first_name' => 'Grand',
                'last_name' => 'Parent',
            ])
        )->assertForbidden();

        $this->assertSame(1, Person::where('household_id', $this->household->getKey())->count());
        $this->assertSame(0, PersonRelationship::count());
    }

    public function test_a_sibling_edge_to_an_existing_child_is_recorded(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->housedBeneficiary($this->pilot, $this->niger);
        $sibling = $this->housedBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian, [
                'related_party_type' => 'existing_beneficiary',
                'related_person_id' => null,
                'related_beneficiary_id' => $sibling->getKey(),
            ])
        )->assertRedirect(route('beneficiaries.show', $record));

        $edge = PersonRelationship::sole();

        // The counterpart child's roster row is created on the fly, so the
        // person side of the edge is real even when the sibling had no row.
        $this->assertSame($sibling->fresh()->person_id, $edge->person_id);
        $this->assertSame($record->getKey(), $edge->related_beneficiary_id);
        $this->assertNotNull($sibling->fresh()->person_id);
    }

    public function test_a_new_person_is_created_and_linked_in_one_request(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->housedBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian, [
                'related_party_type' => 'new_person',
                'related_person_id' => null,
                'first_name' => 'Grand',
                'last_name' => 'Parent',
                'sex' => 'female',
            ])
        )->assertRedirect(route('beneficiaries.show', $record));

        $edge = PersonRelationship::sole();
        $created = $edge->person()->first();

        $this->assertSame('Grand', $created->first_name);
        $this->assertSame($this->household->getKey(), $created->household_id);
        $this->assertSame($record->getKey(), $edge->related_beneficiary_id);
    }

    public function test_an_edge_may_not_point_at_both_a_person_and_a_child(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $other = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian, [
                'related_beneficiary_id' => $other->getKey(),
            ])
        )->assertSessionHasErrors('related_person_id');

        $this->assertSame(0, PersonRelationship::count());
    }

    public function test_an_edge_with_no_counterpart_is_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian, ['related_person_id' => null])
        )->assertSessionHasErrors('related_person_id');

        $this->assertSame(0, PersonRelationship::count());
    }

    public function test_a_child_cannot_be_related_to_their_own_roster_row(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->housedBeneficiary($this->pilot, $this->niger);
        $ownRow = Person::create([
            'household_id' => $this->household->getKey(),
            'first_name' => 'Test',
            'last_name' => 'Child',
        ]);
        $record->person()->associate($ownRow);
        $record->save();

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($ownRow)
        )->assertSessionHasErrors('related_person_id');

        $this->assertSame(0, PersonRelationship::count());
    }

    public function test_a_duplicate_open_edge_is_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian)
        );

        // The same kind of edge to the same person, still open, is a shadow
        // row: it must be ended or amended, not duplicated.
        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian)
        )->assertSessionHasErrors('relationship_type_id');

        $this->assertSame(1, PersonRelationship::count());
    }

    public function test_an_out_of_scope_counterpart_is_refused_at_submit(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $farawayHousehold = $this->makeHousehold($this->nasarawa);
        $distant = Person::create([
            'household_id' => $farawayHousehold->getKey(),
            'first_name' => 'Far',
            'last_name' => 'Away',
        ]);

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($distant)
        )->assertSessionHasErrors('related_person_id');

        $this->assertSame(0, PersonRelationship::count());
    }

    public function test_ending_a_relationship_dates_it_rather_than_deleting_it(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian)
        );
        $edge = PersonRelationship::sole();

        $this->actingAs($officer)->get(route('beneficiaries.relationships.end', [$record, $edge]))
            ->assertOk();
        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.end.confirm', [$record, $edge]),
            ['end_date' => now()->toDateString()]
        )->assertRedirect(route('beneficiaries.show', $record));

        // The row stays — the link existed, and now it has stopped.
        $this->assertDatabaseHas('person_relationships', ['id' => $edge->getKey()]);
        $this->assertNotNull($edge->fresh()->end_date);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'beneficiary_relationship_updated',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_the_end_date_cannot_predate_when_the_link_began(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian, ['start_date' => now()->toDateString()])
        );
        $edge = PersonRelationship::sole();

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.end.confirm', [$record, $edge]),
            ['end_date' => now()->subDay()->toDateString()]
        )->assertSessionHasErrors('end_date');

        $this->assertNull($edge->fresh()->end_date);
    }

    public function test_an_already_ended_relationship_cannot_be_ended_again(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.store', $record),
            $this->relationshipPayload($this->guardian)
        );
        $edge = PersonRelationship::sole();
        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.end.confirm', [$record, $edge]),
            ['end_date' => now()->toDateString()]
        );

        // Ending twice would rewrite the record of when the arrangement
        // stopped — the policy refuses an already-ended link.
        $this->actingAs($officer)->post(
            route('beneficiaries.relationships.end.confirm', [$record, $edge]),
            ['end_date' => now()->toDateString()]
        )->assertForbidden();
    }
}