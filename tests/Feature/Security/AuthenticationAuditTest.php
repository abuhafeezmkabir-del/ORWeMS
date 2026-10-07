<?php

namespace Tests\Feature\Security;

use App\Enums\SecurityEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Phase 3D — authentication security events reach the audit trail.
 *
 * Every listener is wired to a FRAMEWORK event rather than to a controller
 * (see AppServiceProvider), so these tests also assert the wiring: if someone
 * later adds a second sign-in path and forgets to audit it, the framework
 * event still fires and the row still appears.
 */
class AuthenticationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_sign_in_is_audited(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertRedirect(route('dashboard'));

        $row = AuditLog::query()->where('event', 'login_success')->firstOrFail();

        $this->assertSame($user->getKey(), $row->user_id);
        $this->assertNull($row->subject_id);
        $this->assertSame('web', $row->context['guard']);
        $this->assertNotNull($row->request_id);
    }

    public function test_a_failed_sign_in_is_audited_without_the_attempted_password(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'WrongPassword123',
        ])->assertSessionHasErrors('email');

        $row = AuditLog::query()->where('event', 'login_failed')->firstOrFail();

        // A guest has no actor; the account that was probed is the subject.
        $this->assertNull($row->user_id);
        $this->assertSame($user->getKey(), $row->subject_id);
        $this->assertSame('operator@wichov.test', $row->context['email']);
        $this->assertTrue($row->context['account_matched']);

        $serialised = (string) json_encode($row->getAttributes());
        $this->assertStringNotContainsString('WrongPassword123', $serialised);
        $this->assertStringNotContainsString('ValidPassword123', $serialised);
    }

    public function test_a_guess_at_an_unknown_address_links_no_account(): void
    {
        $this->post(route('login.store'), [
            'email' => 'nobody@wichov.test',
            'password' => 'Whatever123456',
        ])->assertSessionHasErrors('email');

        $row = AuditLog::query()->where('event', 'login_failed')->firstOrFail();

        $this->assertNull($row->subject_id);
        $this->assertSame('nobody@wichov.test', $row->context['email']);
        $this->assertFalse($row->context['account_matched']);
        $this->assertStringNotContainsString(
            'Whatever123456',
            (string) json_encode($row->getAttributes())
        );
    }

    public function test_signing_out_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $row = AuditLog::query()->where('event', 'logout')->firstOrFail();

        $this->assertSame($user->getKey(), $row->user_id);
        $this->assertSame('web', $row->context['guard']);
    }

    public function test_repeated_failures_are_audited_as_throttling(): void
    {
        User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        // Five misses fill LoginRequest's own limiter; the sixth is refused
        // before a credential is even looked at, and that refusal is the event
        // an investigator most needs to see.
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'operator@wichov.test',
                'password' => 'WrongPassword123',
            ])->assertSessionHasErrors('email');
        }

        $this->assertSame(5, AuditLog::query()->where('event', 'login_failed')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'login_throttled')->count());

        $throttle = AuditLog::query()->where('event', 'login_throttled')->firstOrFail();
        $this->assertSame('operator@wichov.test', $throttle->context['email']);
    }

    public function test_requesting_a_reset_link_is_audited_and_still_does_not_enumerate(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'operator@wichov.test']);

        $this->post(route('password.email'), ['email' => 'operator@wichov.test'])
            ->assertSessionHas('status');

        $this->post(route('password.email'), ['email' => 'nobody@wichov.test'])
            ->assertSessionHas('status');

        $rows = AuditLog::query()->where('event', 'password_reset_requested')->get();

        $this->assertCount(2, $rows);

        // The trail is allowed to know which addresses are real; the reply to
        // the caller must not be — both messages above are the same string.
        $known = $rows->first(fn ($r) => $r->context['email'] === 'operator@wichov.test');
        $unknown = $rows->first(fn ($r) => $r->context['email'] === 'nobody@wichov.test');

        $this->assertTrue($known->context['account_matched']);
        $this->assertFalse($unknown->context['account_matched']);
        $this->assertNull($known->user_id);
    }

    public function test_a_completed_password_reset_is_audited_against_the_account(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'OldPassword123',
        ]);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ])->assertRedirect(route('login'));

        $row = AuditLog::query()->where('event', 'password_reset')->firstOrFail();

        // Nobody is signed in during a reset, so there is no actor; the
        // affected account is the subject.
        $this->assertNull($row->user_id);
        $this->assertSame($user->getKey(), $row->subject_id);
        $this->assertNotNull($user->fresh()->password_changed_at);
    }

    public function test_a_disabled_account_refusal_is_audited_before_the_session_ends(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        // The order matters: account_disabled must precede logout, otherwise
        // the trail would show a voluntary sign-out and nothing else.
        $events = AuditLog::query()->orderBy('id')->pluck('event')->all();

        $this->assertSame(['account_disabled', 'logout'], $events);

        $refusal = AuditLog::query()->where('event', 'account_disabled')->firstOrFail();
        $this->assertSame($user->getKey(), $refusal->user_id);
        $this->assertSame($user->getKey(), $refusal->subject_id);
    }

    /**
     * Phase 3D deliberately does not implement second-factor authentication.
     * The flag and the reserved vocabulary entry exist so a later phase does
     * not have to invent either, and this test keeps the two halves honest:
     * the switch defaults to off, and no application code reads it yet.
     */
    public function test_second_factor_is_off_by_default_and_not_yet_honoured(): void
    {
        $this->assertFalse((bool) config('orwems.security.two_factor.enabled'));

        $this->assertStringContainsString(
            'ORWEMS_TWO_FACTOR_ENABLED',
            (string) file_get_contents(base_path('.env.example'))
        );

        $offenders = [];

        // A plain file scan: the flag must not be read by any application
        // code until second-factor authentication is actually built.
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), 'orwems.security.two_factor')) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'No code honours the 2FA flag yet, and the docs say so.');

        $this->assertSame(
            0,
            AuditLog::query()->where('event', SecurityEvent::TwoFactorChanged->value)->count()
        );
    }

    /**
     * Guards against double registration. Laravel auto-discovers listeners in
     * app/Listeners on top of whatever AppServiceProvider registers by hand,
     * and for a while both were in force: every auth event produced two audit
     * rows, two session flushes and two timestamp stamps. The idempotent ones
     * hid it; the audit trail did not.
     */
    public function test_each_audit_listener_fires_exactly_once_per_event(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'WrongPassword123',
        ]);

        $this->assertSame(1, AuditLog::query()->where('event', 'login_failed')->count());

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->assertSame(1, AuditLog::query()->where('event', 'login_success')->count());

        $this->actingAs($user)->post(route('logout'));

        $this->assertSame(1, AuditLog::query()->where('event', 'logout')->count());
        $this->assertSame(3, AuditLog::query()->count());
    }
}