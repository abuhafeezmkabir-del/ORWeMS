<?php

namespace Tests\Feature\Care;

use App\Enums\UserStatus;
use App\Models\Placement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Care\Concerns\BuildsCareFixtures;
use Tests\TestCase;

/**
 * Phase 5 — who may work with care placements, and through which door.
 *
 * The same two-step chain as Phase 4: route middleware (permission) then
 * policy (permission AND beneficiary scope), plus the placement-specific
 * lifecycle abilities. Archiving and separation of duties are asserted the
 * same way: a view-only role sees history, cannot write it; a manager can
 * close; only a case officer runs the whole loop; nobody can delete.
 */
class PlacementAuthorizationTest extends TestCase
{
    use BuildsCareFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCareFixtures();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->get(route('beneficiaries.placements.index', $record))
            ->assertRedirect(route('login'));
    }

    public function test_a_disabled_operator_is_refused(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $officer->forceFill(['status' => UserStatus::Disabled])->save();
        $this->app['auth']->forgetGuards();

        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.placements.index', $record))
            ->assertRedirect();
    }

    public function test_permission_without_scope_is_denied(): void
    {
        // Holds placements.view but is scoped to Nasarawa; the child is in Niger.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->nasarawa);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.placements.index', $record))
            ->assertForbidden();
    }

    public function test_a_view_only_role_sees_history_but_cannot_write_it(): void
    {
        // A registration officer holds placements.view only — intake staff
        // know where a child is, but cannot record or amend placements.
        $registrar = $this->scopedOperator('registration_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($registrar)->get(route('beneficiaries.placements.index', $record))
            ->assertOk();
        $this->actingAs($registrar)->get(route('beneficiaries.placements.create', $record))
            ->assertForbidden();
        $this->actingAs($registrar)->post(
            route('beneficiaries.placements.store', $record),
            $this->placementPayload()
        )->assertForbidden();

        $this->assertSame(0, Placement::count());
    }

    public function test_a_case_officer_may_record_a_placement_and_it_is_audited(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);

        $this->actingAs($officer)->get(route('beneficiaries.placements.create', $record))
            ->assertOk();
        $this->actingAs($officer)->post(
            route('beneficiaries.placements.store', $record),
            $this->placementPayload()
        )->assertRedirect(route('beneficiaries.show', $record));

        $placement = Placement::sole();
        $this->assertTrue($placement->isActive());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'care_placement_created',
            'user_id' => $officer->getKey(),
        ]);
    }

    public function test_a_manager_may_view_and_close_but_not_create(): void
    {
        // Separation of duties: the manager holds placements.view and
        // placements.close, not placements.create — closing is the
        // consequential capability, opening a new arrangement is casework.
        $manager = $this->scopedOperator('programme_manager', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $placement = $this->makePlacement($record);

        $this->actingAs($manager)->get(route('beneficiaries.placements.index', $record))
            ->assertOk();
        $this->actingAs($manager)->get(route('beneficiaries.placements.create', $record))
            ->assertForbidden();
        $this->actingAs($manager)->post(
            route('beneficiaries.placements.store', $record),
            $this->placementPayload()
        )->assertForbidden();
        $this->actingAs($manager)->get(
            route('beneficiaries.placements.close', [$record, $placement])
        )->assertOk();
    }

    public function test_an_archived_child_gets_no_new_placement(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $record->delete();

        $this->actingAs($officer)->get(route('beneficiaries.placements.create', $record))
            ->assertForbidden();
        $this->assertSame(0, Placement::count());
    }

    public function test_a_placement_id_under_another_child_is_a_404(): void
    {
        // A cross-record id in the URL is a mistake (or an attempt); 403
        // would confirm the id exists, so it is answered with 404.
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $siblingRecord = $this->makeBeneficiary($this->pilot, $this->niger);
        $placement = $this->makePlacement($siblingRecord);

        $this->actingAs($officer)->get(
            route('beneficiaries.placements.edit', [$record, $placement])
        )->assertNotFound();
        $this->actingAs($officer)->put(
            route('beneficiaries.placements.update', [$record, $placement]),
            $this->placementPayload()
        )->assertNotFound();
    }

    public function test_destructive_deletion_is_unavailable(): void
    {
        $officer = $this->scopedOperator('case_officer', $this->pilot, $this->niger);
        $record = $this->makeBeneficiary($this->pilot, $this->niger);
        $placement = $this->makePlacement($record);

        $this->actingAs($officer)->delete(
            '/beneficiaries/'.$record->beneficiary_ref.'/placements/'.$placement->getKey()
        )->assertStatus(405);
        $this->assertDatabaseHas('placements', ['id' => $placement->getKey()]);
    }
}