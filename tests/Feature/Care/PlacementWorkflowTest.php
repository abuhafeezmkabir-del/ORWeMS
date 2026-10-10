<?php

namespace Tests\Feature\Care;

use App\Enums\PlacementStatus;
use App\Models\Placement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Care\Concerns\BuildsCareFixtures;
use Tests\TestCase;

/**
 * Phase 5 — the placement lifecycle itself.
 *
 * What the tests pin down: one active placement per child (readable refusal
 * first, database rule second), closing as its own terminal step that KEEPS
 * the row, the change transaction (close + open commit together or not at
 * all), amendments that cannot smuggle a closure, and input-level scope on
 * the household/caregiver a placement names.
 */
class PlacementWorkflowTest extends TestCase
{
    use BuildsCareFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCareFixtures();
    }

    public function test_a_placement_opens_active_and_a_second_one_is_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->post(
            route('beneficiaries.placements.store', $record),
            $this->placementPayload()
        )->assertRedirect(route('beneficiaries.show', $record));

        $this->actingAs($officer)->post(
            route('beneficiaries.placements.store', $record),
            $this->placementPayload()
        )->assertSessionHasErrors('start_date');

        $this->assertSame(1, Placement::count());
        $this->assertTrue(Placement::sole()->isActive());
    }

    public function test_the_single_active_rule_is_a_model_guard_with_a_readable_message(): void
    {
        // The friendly layer before the database constraint: a direct model
        // write (console, tinker, another code path) still gets the
        // caseworker-facing instruction, not a driver error.
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $this->makePlacement($record);

        try {
            $this->makePlacement($record);
            $this->fail('A second active placement should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'already has an active placement',
                $exception->errors()['start_date'][0] ?? ''
            );
        }
    }

    public function test_closing_dates_the_placement_and_keeps_the_row_as_history(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $placement = $this->makePlacement($record);

        $this->actingAs($officer)->get(
            route('beneficiaries.placements.close', [$record, $placement])
        )->assertOk();

        $this->actingAs($officer)->post(
            route('beneficiaries.placements.close.confirm', [$record, $placement]),
            ['end_date' => now()->toDateString(), 'closure_reason' => 'Returned to family']
        )->assertRedirect(route('beneficiaries.show', $record));

        $placement->refresh();
        $this->assertFalse($placement->isActive());
        $this->assertSame(PlacementStatus::Closed, $placement->status);
        $this->assertSame(now()->toDateString(), $placement->end_date->toDateString());
        $this->assertSame('Returned to family', $placement->closure_reason);
        $this->assertDatabaseHas('placements', ['id' => $placement->getKey()]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'care_placement_closed',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_a_closed_placement_cannot_be_amended(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $placement = $this->closePlacementDirectly($this->makePlacement($record));

        $this->actingAs($officer)->get(
            route('beneficiaries.placements.edit', [$record, $placement])
        )->assertForbidden();
        $this->actingAs($officer)->put(
            route('beneficiaries.placements.update', [$record, $placement]),
            $this->placementPayload()
        )->assertForbidden();

        $this->assertSame('Initial placement', $placement->fresh()->placement_reason);
    }

    public function test_the_active_placement_can_be_amended_but_not_smuggled_closed(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $placement = $this->makePlacement($record);

        // end_date/status are not in the request rules and not model-fillable:
        // two independent refusals of an amendment that tries to close.
        $this->actingAs($officer)->put(
            route('beneficiaries.placements.update', [$record, $placement]),
            $this->placementPayload([
                'placement_reason' => 'Caregiver identified as aunt',
                'end_date' => now()->subDay()->toDateString(),
                'status' => 'closed',
            ])
        )->assertRedirect(route('beneficiaries.show', $record));

        $placement->refresh();
        $this->assertSame('Caregiver identified as aunt', $placement->placement_reason);
        $this->assertTrue($placement->isActive());
        $this->assertNull($placement->end_date);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'care_placement_updated',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_change_closes_and_opens_in_one_transaction(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $current = $this->makePlacement($record);

        $response = $this->actingAs($officer)->post(
            route('beneficiaries.placements.change.store', $record),
            $this->placementPayload([
                'current_end_date' => now()->toDateString(),
                'current_closure_reason' => 'Temporary placement ended',
                'start_date' => now()->toDateString(),
                'placement_reason' => 'Moved to kinship care with aunt',
            ])
        );
        $response->assertRedirect(route('beneficiaries.show', $record));

        $current->refresh();
        $this->assertSame(PlacementStatus::Closed, $current->status);
        $this->assertSame('Temporary placement ended', $current->closure_reason);

        $open = $record->placements()->whereNull('end_date')->get();
        $this->assertCount(1, $open);
        $this->assertSame('Moved to kinship care with aunt', $open->first()->placement_reason);

        // Both halves leave a trail, tagged as one change.
        $this->assertSame(2, \App\Models\AuditLog::where('user_id', $officer->getKey())
            ->whereIn('event', ['care_placement_closed', 'care_placement_created'])
            ->count());
    }

    public function test_a_contradictory_change_is_refused_and_nothing_moves(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $current = $this->makePlacement($record);

        // The new placement cannot start before the current one ends.
        $this->actingAs($officer)->post(
            route('beneficiaries.placements.change.store', $record),
            $this->placementPayload([
                'current_end_date' => now()->toDateString(),
                'start_date' => now()->subDay()->toDateString(),
                'placement_reason' => 'Overlapping move',
            ])
        )->assertSessionHasErrors('start_date');

        $this->assertSame(1, Placement::count());
        $this->assertTrue($current->fresh()->isActive());
    }

    public function test_closure_dates_are_bounded(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $placement = $this->makePlacement($record);

        // A future closure means the child is somewhere they are not.
        $this->actingAs($officer)->post(
            route('beneficiaries.placements.close.confirm', [$record, $placement]),
            ['end_date' => now()->addDay()->toDateString()]
        )->assertSessionHasErrors('end_date');

        // And it cannot end before it began.
        $this->actingAs($officer)->post(
            route('beneficiaries.placements.close.confirm', [$record, $placement]),
            ['end_date' => now()->subDays(30)->toDateString()]
        )->assertSessionHasErrors('end_date');

        $this->assertTrue($placement->fresh()->isActive());
    }

    public function test_an_out_of_scope_household_is_refused_at_submit(): void
    {
        // The form never offers it, and the submit check refuses it anyway —
        // a form is not a control. The caregiver stays in scope so the
        // household is the field under test.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $faraway = $this->makeHousehold($this->nasarawa);

        $this->actingAs($officer)->post(
            route('beneficiaries.placements.store', $record),
            $this->placementPayload(['household_id' => $faraway->getKey()])
        )->assertSessionHasErrors('household_id');

        $this->assertSame(0, Placement::count());
    }

    public function test_a_withdrawn_arrangement_kind_cannot_be_chosen(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $this->kinship->update(['is_active' => false]);

        $this->actingAs($officer)->post(
            route('beneficiaries.placements.store', $record),
            $this->placementPayload()
        )->assertSessionHasErrors('care_arrangement_type_id');

        $this->assertSame(0, Placement::count());
    }

    public function test_change_without_an_active_placement_is_a_404(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.placements.change', $record))
            ->assertNotFound();
    }

    public function test_a_change_needs_both_capabilities(): void
    {
        // Changing is closing AND creating: the manager holds close but not
        // create, so the compound action is refused outright.
        $manager = $this->scopedOperator('programme_manager', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $this->makePlacement($record);

        $this->actingAs($manager)->get(route('beneficiaries.placements.change', $record))
            ->assertForbidden();
    }

    public function test_the_case_file_page_renders_the_care_cards(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->housedBeneficiary($this->pilot, $this->niger);
        $placement = $this->makePlacement($record);

        $response = $this->actingAs($officer)->get(route('beneficiaries.show', $record));
        $response->assertOk();
        $response->assertSee($placement->careArrangementType->name);
    }
}