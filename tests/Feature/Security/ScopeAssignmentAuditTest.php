<?php

namespace Tests\Feature\Security;

use App\Enums\SecurityEvent;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\BuildsScopeFixtures;
use Tests\TestCase;

/**
 * Phase 3D — responsibility and reach changes reach the audit trail.
 *
 * Phase 3B grants and 3C scoping were built first, audited second, so this
 * file pins the wiring at the point where the two meet: the same
 * administrative call that changes what an account may touch must also leave
 * the row saying so — and only when the call actually changed something. A
 * no-op that records an event is a trail that cries wolf; an event with no
 * actor when a signed-in administrator made the call is a trail that hides
 * the hand.
 */
class ScopeAssignmentAuditTest extends TestCase
{
    use BuildsScopeFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles come from the seeded catalogue; the vocabulary of the trail is
        // stable codes, not ad-hoc fixtures.
        $this->seed([PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class]);
    }

    private function role(string $code): Role
    {
        return Role::query()->where('code', $code)->firstOrFail();
    }

    public function test_a_role_grant_is_audited_with_actor_code_and_subject(): void
    {
        $admin = User::factory()->create();
        $subject = User::factory()->create();

        $this->actingAs($admin);
        $subject->assignRole($this->role('system_administrator'));

        $row = AuditLog::query()
            ->where('event', SecurityEvent::RoleAssignment->value)
            ->firstOrFail();

        $this->assertSame($admin->getKey(), $row->user_id);
        $this->assertSame($subject->getKey(), $row->subject_id);
        $this->assertSame('system_administrator', $row->context['role_code']);
    }

    public function test_repeating_a_role_grant_records_nothing_because_nothing_happened(): void
    {
        $subject = User::factory()->create();

        $subject->assignRole($this->role('auditor'));

        $this->assertSame(1, AuditLog::query()->where('event', SecurityEvent::RoleAssignment->value)->count());

        $subject->assignRole($this->role('auditor'));
        $subject->assignRole($this->role('auditor'));

        $this->assertSame(
            1,
            AuditLog::query()->where('event', SecurityEvent::RoleAssignment->value)->count(),
            'Idempotent re-grants are not events.'
        );
    }

    public function test_granting_and_withdrawing_a_programme_scope_is_audited(): void
    {
        $admin = User::factory()->create();
        $subject = User::factory()->create();
        $programme = $this->makeProgramme('WICHOV-PILOT');

        $this->actingAs($admin);
        $subject->assignProgrammeScope($programme);

        $grant = AuditLog::query()
            ->where('event', SecurityEvent::ProgrammeScopeGranted->value)
            ->firstOrFail();

        $this->assertSame($admin->getKey(), $grant->user_id);
        $this->assertSame($subject->getKey(), $grant->subject_id);
        $this->assertSame($programme->getKey(), $grant->context['programme_id']);

        $subject->revokeProgrammeScope($programme);

        $revoke = AuditLog::query()
            ->where('event', SecurityEvent::ProgrammeScopeRevoked->value)
            ->firstOrFail();

        $this->assertSame($admin->getKey(), $revoke->user_id);
        $this->assertSame($subject->getKey(), $revoke->subject_id);
    }

    public function test_a_repeated_scope_grant_and_a_repeated_revocation_record_nothing(): void
    {
        $subject = User::factory()->create();
        $programme = $this->makeProgramme('WICHOV-PILOT');

        $subject->assignProgrammeScope($programme);
        $subject->assignProgrammeScope($programme);
        $subject->revokeProgrammeScope($programme);
        $subject->revokeProgrammeScope($programme);

        $this->assertSame(
            1,
            AuditLog::query()->where('event', SecurityEvent::ProgrammeScopeGranted->value)->count()
        );
        $this->assertSame(
            1,
            AuditLog::query()->where('event', SecurityEvent::ProgrammeScopeRevoked->value)->count()
        );
    }

    public function test_reactivating_a_withdrawn_scope_is_audited_as_a_grant(): void
    {
        $subject = User::factory()->create();
        $programme = $this->makeProgramme('WICHOV-PILOT');

        $subject->assignProgrammeScope($programme);
        $subject->revokeProgrammeScope($programme);
        $subject->assignProgrammeScope($programme);

        $this->assertSame(
            2,
            AuditLog::query()->where('event', SecurityEvent::ProgrammeScopeGranted->value)->count(),
            'Re-activation restores reach, so it is an event.'
        );
    }

    public function test_granting_and_withdrawing_a_geographic_scope_is_audited(): void
    {
        $admin = User::factory()->create();
        $subject = User::factory()->create();
        $state = $this->makeState($this->makeCountry(), 'Niger State');

        $this->actingAs($admin);
        $subject->assignGeographicScope($state);

        $grant = AuditLog::query()
            ->where('event', SecurityEvent::GeographicScopeGranted->value)
            ->firstOrFail();

        $this->assertSame($admin->getKey(), $grant->user_id);
        $this->assertSame($subject->getKey(), $grant->subject_id);
        $this->assertSame('state', $grant->context['level']);
        $this->assertSame($state->getKey(), $grant->context['geography_id']);

        $subject->revokeGeographicScope($state);

        $revoke = AuditLog::query()
            ->where('event', SecurityEvent::GeographicScopeRevoked->value)
            ->firstOrFail();

        $this->assertSame($admin->getKey(), $revoke->user_id);
        $this->assertSame($subject->getKey(), $revoke->subject_id);
    }

    public function test_repeated_geographic_grants_and_revocations_record_nothing(): void
    {
        $subject = User::factory()->create();
        $country = $this->makeCountry();

        $subject->assignGeographicScope($country);
        $subject->assignGeographicScope($country);
        $subject->revokeGeographicScope($country);
        $subject->revokeGeographicScope($country);

        $this->assertSame(
            1,
            AuditLog::query()->where('event', SecurityEvent::GeographicScopeGranted->value)->count()
        );
        $this->assertSame(
            1,
            AuditLog::query()->where('event', SecurityEvent::GeographicScopeRevoked->value)->count()
        );
    }

    public function test_a_scope_change_without_a_signed_in_actor_records_a_null_actor(): void
    {
        // Console runs, seeders and queued maintenance have no session: the
        // affected account is still recorded as the subject even when there
        // is nobody to name as the actor.
        $subject = User::factory()->create();

        $subject->assignProgrammeScope($this->makeProgramme('WICHOV-PILOT'));

        $row = AuditLog::query()
            ->where('event', SecurityEvent::ProgrammeScopeGranted->value)
            ->firstOrFail();

        $this->assertNull($row->user_id);
        $this->assertSame($subject->getKey(), $row->subject_id);
    }
}
