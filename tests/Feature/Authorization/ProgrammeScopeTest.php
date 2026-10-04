<?php

namespace Tests\Feature\Authorization;

use App\Enums\ProgrammeStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\BuildsScopeFixtures;
use Tests\TestCase;

/**
 * Phase 3C — programme scope.
 *
 * A programme assignment says which programmes an operator may work in. These
 * tests pin the two directions that matter: a current assignment grants access
 * to exactly that programme, and everything else — no assignment, a withdrawn
 * assignment, a programme that is no longer operating, an unrelated programme —
 * is refused.
 *
 * The last test in this file is the one that keeps the whole design honest:
 * holding a scope must not confer a single capability. Scope narrows; it never
 * grants.
 */
class ProgrammeScopeTest extends TestCase
{
    use BuildsScopeFixtures, RefreshDatabase;

    public function test_an_account_with_no_programme_scope_is_denied(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();

        $this->assertFalse($user->hasProgrammeAccess($programme));
        $this->assertFalse($user->hasProgrammeAccess($programme->getKey()));
    }

    public function test_an_active_programme_assignment_grants_access(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();

        $user->assignProgrammeScope($programme);

        // Both the model and a bare id are accepted, so future policies can pass
        // whichever a domain record carries.
        $this->assertTrue($user->hasProgrammeAccess($programme));
        $this->assertTrue($user->hasProgrammeAccess($programme->getKey()));
    }

    public function test_an_assignment_to_one_programme_does_not_grant_another(): void
    {
        $user = User::factory()->create();
        $pilot = $this->makeProgramme('WICHOV-PILOT');
        $other = $this->makeProgramme('WICHOV-OTHER');

        $user->assignProgrammeScope($pilot);

        $this->assertTrue($user->hasProgrammeAccess($pilot));
        $this->assertFalse($user->hasProgrammeAccess($other));
    }

    public function test_multiple_programme_assignments_all_grant_access(): void
    {
        $user = User::factory()->create();
        $first = $this->makeProgramme('WICHOV-A');
        $second = $this->makeProgramme('WICHOV-B');
        $unassigned = $this->makeProgramme('WICHOV-C');

        $user->assignProgrammeScope($first);
        $user->assignProgrammeScope($second);

        $this->assertTrue($user->hasProgrammeAccess($first));
        $this->assertTrue($user->hasProgrammeAccess($second));
        $this->assertFalse($user->hasProgrammeAccess($unassigned));
        $this->assertCount(2, $user->fresh()->programmes);
    }

    public function test_a_withdrawn_assignment_grants_nothing_but_survives_on_record(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();

        $user->assignProgrammeScope($programme);
        $this->assertTrue($user->hasProgrammeAccess($programme));

        $user->revokeProgrammeScope($programme);

        $this->assertFalse($user->hasProgrammeAccess($programme));

        // Revocation is a deactivation, not a deletion: who was once assigned to
        // the programme is part of the record of what happened.
        $this->assertDatabaseCount('programme_user', 1);
        $this->assertFalse((bool) $user->fresh()->programmes->first()->pivot->is_active);
    }

    public function test_a_programme_that_is_not_operating_grants_no_access(): void
    {
        $statuses = [
            ProgrammeStatus::Planned,
            ProgrammeStatus::Suspended,
            ProgrammeStatus::Closed,
        ];

        foreach ($statuses as $status) {
            $user = User::factory()->create();
            $programme = $this->makeProgramme('WICHOV-'.strtoupper($status->value), $status);

            $user->assignProgrammeScope($programme);

            $this->assertFalse(
                $user->hasProgrammeAccess($programme),
                "An assignment to a {$status->value} programme must not grant access."
            );
        }
    }

    public function test_reassigning_an_already_assigned_programme_is_idempotent(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();

        $user->assignProgrammeScope($programme);
        $user->assignProgrammeScope($programme);
        $user->assignProgrammeScope($programme->getKey());

        // One row, not three: repeated seeding or repeated administrative action
        // must not accumulate duplicates.
        $this->assertDatabaseCount('programme_user', 1);
        $this->assertCount(1, $user->fresh()->programmes);
    }

    public function test_reassignment_reactivates_a_withdrawn_assignment(): void
    {
        $user = User::factory()->create();
        $programme = $this->makeProgramme();

        $user->assignProgrammeScope($programme);
        $user->revokeProgrammeScope($programme);
        $this->assertFalse($user->hasProgrammeAccess($programme));

        $user->assignProgrammeScope($programme);

        $this->assertTrue($user->hasProgrammeAccess($programme));
        $this->assertDatabaseCount('programme_user', 1);
    }

    public function test_an_unknown_programme_id_grants_nothing(): void
    {
        $user = User::factory()->create();
        $user->assignProgrammeScope($this->makeProgramme());

        $this->assertFalse($user->hasProgrammeAccess(999999));
    }

    public function test_the_active_programmes_relationship_lists_only_live_assignments(): void
    {
        $user = User::factory()->create();
        $live = $this->makeProgramme('A-LIVE');
        $withdrawn = $this->makeProgramme('B-WITHDRAWN');
        $closed = $this->makeProgramme('C-CLOSED', ProgrammeStatus::Closed);

        $user->assignProgrammeScope($live);
        $user->assignProgrammeScope($withdrawn);
        $user->revokeProgrammeScope($withdrawn);
        $user->assignProgrammeScope($closed);

        $this->assertSame(
            ['A-LIVE'],
            $user->activeProgrammes()->orderBy('programmes.code')->pluck('programmes.code')->all()
        );
    }

    public function test_programme_scope_never_grants_a_permission(): void
    {
        $user = User::factory()->create();
        $user->assignProgrammeScope($this->makeProgramme());

        // The central claim of Phase 3C: scope narrows authority, it does not
        // create it. An account scoped to every programme still holds nothing
        // unless a role grants it.
        $this->assertSame([], $user->permissionCodes()->all());
        $this->assertFalse($user->hasPermission('beneficiaries.view'));
        $this->assertFalse($user->can('beneficiaries.view'));
    }
}
