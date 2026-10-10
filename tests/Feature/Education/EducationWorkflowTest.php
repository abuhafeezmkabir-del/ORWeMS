<?php

namespace Tests\Feature\Education;

use App\Models\EducationEnrolment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Education\Concerns\BuildsEducationFixtures;
use Tests\TestCase;

/**
 * Phase 6A — the enrolment lifecycle itself.
 *
 * One current enrolment per child (readable refusal first, database rule
 * second), closing as its own terminal step that KEEPS the row, the change
 * transaction (close + open commit together or not at all), and amendments
 * that cannot smuggle a closure.
 */
class EducationWorkflowTest extends TestCase
{
    use BuildsEducationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEducationFixtures();
    }

    public function test_an_enrolment_opens_current_and_a_second_is_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.education.store', $record),
            $this->enrolmentPayload()
        )->assertRedirect(route('beneficiaries.show', $record));

        $this->actingAs($officer)->post(
            route('beneficiaries.education.store', $record),
            $this->enrolmentPayload()
        )->assertSessionHasErrors('start_date');

        $this->assertSame(1, EducationEnrolment::count());
        $this->assertTrue(EducationEnrolment::sole()->isCurrent());
    }

    public function test_the_single_current_rule_is_a_model_guard(): void
    {
        // The friendly layer before the database constraint: a direct model
        // write still gets the caseworker-facing instruction, not a driver
        // error.
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $this->makeEnrolment($record);

        try {
            $this->makeEnrolment($record);
            $this->fail('A second current enrolment should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'already has a current enrolment',
                $exception->errors()['start_date'][0] ?? ''
            );
        }
    }

    public function test_closing_dates_the_enrolment_and_keeps_the_row(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $enrolment = $this->makeEnrolment($record);

        $this->actingAs($officer)->get(
            route('beneficiaries.education.close', [$record, $enrolment])
        )->assertOk();

        $this->actingAs($officer)->post(
            route('beneficiaries.education.close.confirm', [$record, $enrolment]),
            ['end_date' => now()->toDateString(), 'closure_reason' => 'Moved schools']
        )->assertRedirect(route('beneficiaries.show', $record));

        $enrolment = $enrolment->fresh();
        $this->assertFalse($enrolment->isCurrent());
        $this->assertSame('Moved schools', $enrolment->closure_reason);
        $this->assertDatabaseHas('education_enrolments', ['id' => $enrolment->getKey()]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'education_enrolment_closed',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_change_closes_and_opens_in_one_transaction(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $old = $this->makeEnrolment($record, ['start_date' => now()->subYears(2)->toDateString()]);

        $this->actingAs($officer)->get(
            route('beneficiaries.education.change', $record)
        )->assertOk();

        $this->actingAs($officer)->post(
            route('beneficiaries.education.change.store', $record),
            array_merge($this->enrolmentPayload([
                'institution_name' => 'New Secondary School',
                'education_level_id' => $this->secondary->getKey(),
                'start_date' => now()->toDateString(),
            ]), [
                'current_end_date' => now()->toDateString(),
                'current_closure_reason' => 'Moved to secondary',
            ])
        )->assertRedirect(route('beneficiaries.show', $record));

        $this->assertSame(2, EducationEnrolment::count());
        $this->assertFalse($old->fresh()->isCurrent());
        $this->assertSame('Moved to secondary', $old->fresh()->closure_reason);

        $current = $record->enrolments()->whereNull('end_date')->sole();
        $this->assertSame('New Secondary School', $current->institution_name);
        $this->assertDatabaseHas('audit_logs', ['event' => 'education_enrolment_closed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'education_enrolment_created']);
    }

    public function test_an_amendment_cannot_close_or_touch_a_closed_enrolment(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $enrolment = $this->makeEnrolment($record);

        // Smuggled closure fields are not fillable: silently ignored, the row
        // stays current.
        $this->actingAs($officer)->put(
            route('beneficiaries.education.update', [$record, $enrolment]),
            array_merge($this->enrolmentPayload(['class_or_year' => 'Primary 5']), [
                'end_date' => now()->toDateString(),
                'closure_reason' => 'Smuggled',
            ])
        )->assertRedirect(route('beneficiaries.show', $record));

        $enrolment = $enrolment->fresh();
        $this->assertTrue($enrolment->isCurrent());
        $this->assertSame('Primary 5', $enrolment->class_or_year);

        // A closed enrolment cannot be amended at all.
        $this->closeEnrolmentDirectly($enrolment);

        $this->actingAs($officer)->get(
            route('beneficiaries.education.edit', [$record, $enrolment])
        )->assertForbidden();
        $this->actingAs($officer)->put(
            route('beneficiaries.education.update', [$record, $enrolment]),
            $this->enrolmentPayload()
        )->assertForbidden();
    }

    public function test_a_closure_before_the_start_date_is_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $enrolment = $this->makeEnrolment($record, ['start_date' => now()->toDateString()]);

        $this->actingAs($officer)->post(
            route('beneficiaries.education.close.confirm', [$record, $enrolment]),
            ['end_date' => now()->subWeek()->toDateString()]
        )->assertSessionHasErrors('end_date');

        $this->assertTrue($enrolment->fresh()->isCurrent());
    }

    public function test_invalid_dates_invalid_level_and_withdrawn_level_are_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        // A level id that names nothing.
        $this->actingAs($officer)->post(
            route('beneficiaries.education.store', $record),
            $this->enrolmentPayload(['education_level_id' => 999999])
        )->assertSessionHasErrors('education_level_id');

        // A withdrawn level keeps its history but can no longer be chosen.
        $this->primary->update(['is_active' => false]);

        $this->actingAs($officer)->post(
            route('beneficiaries.education.store', $record),
            $this->enrolmentPayload()
        )->assertSessionHasErrors('education_level_id');

        $this->assertSame(0, EducationEnrolment::count());
    }

    public function test_forged_actor_and_beneficiary_fields_are_derived_server_side(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $sibling = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.education.store', $record),
            array_merge($this->enrolmentPayload(), [
                'beneficiary_id' => $sibling->getKey(),
                'created_by' => 999999,
            ])
        )->assertRedirect(route('beneficiaries.show', $record));

        // None of the forged field names is fillable, so the row still belongs
        // to the URL's child — not the sibling, not nobody.
        $enrolment = EducationEnrolment::sole();
        $this->assertSame($record->getKey(), $enrolment->beneficiary_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'education_enrolment_created',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_an_archived_child_cannot_be_updated_or_closed_either(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $enrolment = $this->makeEnrolment($record);
        $record->delete();

        $this->actingAs($officer)->get(
            route('beneficiaries.education.edit', [$record, $enrolment])
        )->assertForbidden();
        $this->actingAs($officer)->put(
            route('beneficiaries.education.update', [$record, $enrolment]),
            $this->enrolmentPayload(['class_or_year' => 'Primary 5'])
        )->assertForbidden();
        $this->actingAs($officer)->get(
            route('beneficiaries.education.close', [$record, $enrolment])
        )->assertForbidden();
        $this->actingAs($officer)->post(
            route('beneficiaries.education.close.confirm', [$record, $enrolment]),
            ['end_date' => now()->toDateString()]
        )->assertForbidden();

        $this->assertTrue($enrolment->fresh()->isCurrent());
    }

    public function test_a_change_needs_both_close_and_create(): void
    {
        // Changing is closing AND creating: the manager holds close but not
        // create, so the compound action is refused outright.
        $manager = $this->scopedOperator('programme_manager', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $this->makeEnrolment($record);

        $this->actingAs($manager)->get(route('beneficiaries.education.change', $record))
            ->assertForbidden();
    }

    public function test_change_without_a_current_enrolment_is_a_404(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.education.change', $record))
            ->assertNotFound();
    }

    public function test_the_case_file_page_renders_the_education_card(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $enrolment = $this->makeEnrolment($record);

        $response = $this->actingAs($officer)->get(route('beneficiaries.show', $record));
        $response->assertOk();
        $response->assertSee($enrolment->institution_name);
        $response->assertSee($enrolment->educationLevel->name);
    }

}
