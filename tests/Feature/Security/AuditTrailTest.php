<?php

namespace Tests\Feature\Security;

use App\Enums\SecurityEvent;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\Auditor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 3D — the audit trail itself as a security boundary.
 *
 * Two properties are pinned here:
 *
 *  1. Rows are APPEND-ONLY. An audit trail that can be rewritten is not
 *     evidence, it is a narrative, so every delete and update path is refused
 *     at the model and at the builder.
 *  2. Rows are SAFE. Nothing credential-shaped, nothing arbitrary and nothing
 *     unbounded ever reaches the `context` column, no matter what a future
 *     call site passes in.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_single_writer_stores_actor_subject_and_context(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();

        $row = Auditor::record(
            SecurityEvent::RoleAssignment,
            actor: $actor,
            subject: $subject,
            context: ['role_code' => 'system_administrator'],
        );

        $this->assertSame('role_assignment', $row->event);
        $this->assertSame($actor->getKey(), $row->user_id);
        $this->assertSame($subject->getKey(), $row->subject_id);
        $this->assertSame($subject->getMorphClass(), $row->subject_type);
        $this->assertSame('system_administrator', $row->context['role_code']);
        $this->assertNotNull($row->created_at);

        // Append-only means the row has no revision timestamp to revise.
        $this->assertArrayNotHasKey('updated_at', $row->getAttributes());
        $this->assertNull(AuditLog::UPDATED_AT);
    }

    public function test_credential_material_is_stripped_from_context(): void
    {
        $row = Auditor::record(SecurityEvent::LoginFailed, context: [
            'email' => 'operator@wichov.test',
            'password' => 'CorrectHorseBatteryStaple',
            'password_confirmation' => 'CorrectHorseBatteryStaple',
            'remember_token' => 'a-remember-token',
            'api_key' => 'an-api-key',
            'otp' => '123456',
            'reset_token' => 'a-reset-token',
            'csrf_token' => 'a-csrf-token',
            'session_key' => 'a-session-key',
            'bcrypt' => '$2y$10$abcdefghijklmnopqrstuv',
            'nested' => ['password' => 'CorrectHorseBatteryStaple', 'role_code' => 'auditor'],
        ]);

        $context = $row->context;

        $this->assertSame('operator@wichov.test', $context['email']);
        $this->assertSame(['role_code' => 'auditor'], $context['nested']);

        $serialised = (string) json_encode($row->getAttributes());

        $this->assertStringNotContainsString('CorrectHorseBatteryStaple', $serialised);
        $this->assertStringNotContainsString('a-remember-token', $serialised);
        $this->assertStringNotContainsString('an-api-key', $serialised);
        $this->assertStringNotContainsString('a-reset-token', $serialised);
        $this->assertStringNotContainsString('a-csrf-token', $serialised);
        $this->assertStringNotContainsString('a-session-key', $serialised);
        $this->assertStringNotContainsString('$2y$10$', $serialised);
    }

    public function test_objects_are_never_serialised_into_context(): void
    {
        $row = Auditor::record(SecurityEvent::LoginSuccess, context: [
            'when' => Carbon::parse('2026-01-02T03:04:05+00:00'),
            'payload' => (object) ['password' => 'leaked-by-serialization'],
            'kept' => 'yes',
        ]);

        $this->assertSame('yes', $row->context['kept']);
        $this->assertIsString($row->context['when']);
        $this->assertArrayNotHasKey('payload', $row->context);
        $this->assertStringNotContainsString(
            'leaked-by-serialization',
            (string) json_encode($row->getAttributes())
        );
    }

    /* ------------------------------------------------------------------
     | Append-only enforcement
     ------------------------------------------------------------------ */

    public function test_an_audit_row_cannot_be_updated(): void
    {
        $row = Auditor::record(SecurityEvent::LoginSuccess);

        $this->expectException(\LogicException::class);

        $row->update(['event' => 'login_failed']);
    }

    public function test_an_audit_row_cannot_be_deleted(): void
    {
        $row = Auditor::record(SecurityEvent::LoginSuccess);

        $this->expectException(\LogicException::class);

        $row->delete();
    }

    public function test_an_audit_row_cannot_be_force_deleted(): void
    {
        $row = Auditor::record(SecurityEvent::LoginSuccess);

        $this->expectException(\LogicException::class);

        $row->forceDelete();
    }

    public function test_a_mass_delete_through_the_builder_is_refused(): void
    {
        Auditor::record(SecurityEvent::LoginSuccess);
        Auditor::record(SecurityEvent::Logout);

        $this->expectException(\LogicException::class);

        AuditLog::query()->delete();
    }

    public function test_a_mass_update_through_the_builder_is_refused(): void
    {
        Auditor::record(SecurityEvent::LoginSuccess);

        $this->expectException(\LogicException::class);

        AuditLog::query()->update(['event' => 'tampered']);
    }

    /**
     * The second layer: `audit_logs.user_id` is RESTRICT, so an account that has
     * acted in ORWeMS cannot be removed out from under its own record.
     */
    public function test_an_actor_with_an_audit_trail_cannot_be_deleted(): void
    {
        $user = User::factory()->create();

        Auditor::record(SecurityEvent::LoginSuccess, actor: $user);

        $this->expectException(QueryException::class);

        $user->delete();
    }

    /* ------------------------------------------------------------------
     | Request forensics
     ------------------------------------------------------------------ */

    public function test_an_ordinary_page_view_writes_no_audit_rows(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_request_forensics_are_attached_to_rows_written_during_a_request(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->withHeader('User-Agent', 'ORWeMS-Test-Agent/1.0')
            ->withHeader('X-Request-Id', 'req-abcdefgh')
            ->post(route('login.store'), [
                'email' => 'operator@wichov.test',
                'password' => 'ValidPassword123',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertHeader('X-Request-Id', 'req-abcdefgh');

        $row = AuditLog::query()->where('event', 'login_success')->firstOrFail();

        $this->assertSame($user->getKey(), $row->user_id);
        $this->assertSame('req-abcdefgh', $row->request_id);
        $this->assertSame('ORWeMS-Test-Agent/1.0', $row->user_agent);
        $this->assertNotNull($row->ip_address);
    }

    /* ------------------------------------------------------------------
     | Forbidden keys vs audited identifiers
     ------------------------------------------------------------------ */

    /**
     * The forensic allow-list keeps working for the exact vocabulary Phase 3B
     * and 3C rely on — programme_id, geography_id and role_code — so that a
     * future tightening of the credential-key pattern cannot silently eat
     * the very identifiers that make a grant row readable.
     */
    public function test_grant_identifiers_are_kept_even_though_they_resemble_credential_keys(): void
    {
        $row = Auditor::record(SecurityEvent::ProgrammeScopeGranted, context: [
            'programme_id' => 7,
            'geography_id' => 3,
            'role_code' => 'system_administrator',
            'level' => 'state',
            'password' => 'CorrectHorseBatteryStaple',
        ]);

        $this->assertSame(7, $row->context['programme_id']);
        $this->assertSame(3, $row->context['geography_id']);
        $this->assertSame('system_administrator', $row->context['role_code']);
        $this->assertSame('state', $row->context['level']);
        $this->assertArrayNotHasKey('password', $row->context ?? []);

        $this->assertStringNotContainsString(
            'CorrectHorseBatteryStaple',
            (string) json_encode($row->getAttributes())
        );
    }
}