<?php

namespace Tests\Feature\Security;

use App\Enums\SecurityEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 3D — password age policy.
 *
 * The policy is a configuration decision (config/orwems.php) and is OFF by
 * default, so these tests always start by turning it on deliberately. The
 * second half of the contract matters as much as the first: an expired
 * password must not be able to lock an operator out of the only screen that
 * can fix it, nor out of signing out.
 */
class PasswordExpiryTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the policy for the length of one test. */
    private function enablePasswordExpiry(int $days = 180): void
    {
        config()->set('orwems.security.password_expiry.enabled', true);
        config()->set('orwems.security.password_expiry.days', $days);
    }

    public function test_password_expiry_is_off_by_default(): void
    {
        $this->assertFalse((bool) config('orwems.security.password_expiry.enabled'));

        $user = User::factory()->create(['password_changed_at' => now()->subYears(3)]);

        $this->assertFalse($user->passwordMustBeChanged());
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_an_expired_password_is_redirected_to_the_change_screen(): void
    {
        $this->enablePasswordExpiry(180);

        $user = User::factory()->create(['password_changed_at' => now()->subDays(200)]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change'))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, AuditLog::query()->count(), 'A redirect is not a security event.');
    }

    public function test_a_null_password_changed_at_is_grandfathered_not_expired(): void
    {
        $this->enablePasswordExpiry();

        // Every account provisioned before the column existed would otherwise be
        // expired the instant WICHOV flips the flag — including the person who
        // flips it, with no way back in.
        $user = User::factory()->create(['password_changed_at' => null]);

        $this->assertFalse($user->passwordMustBeChanged());
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_a_recently_changed_password_passes(): void
    {
        $this->enablePasswordExpiry(180);

        $user = User::factory()->create(['password_changed_at' => now()->subDays(179)]);

        $this->assertFalse($user->passwordMustBeChanged());
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_a_misconfigured_period_fails_open_rather_than_locking_everyone_out(): void
    {
        $this->enablePasswordExpiry(0);

        $user = User::factory()->create(['password_changed_at' => now()->subYears(3)]);

        $this->assertFalse($user->passwordMustBeChanged());
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_the_change_screen_and_sign_out_stay_reachable_when_expired(): void
    {
        $this->enablePasswordExpiry();

        $user = User::factory()->create(['password_changed_at' => now()->subDays(400)]);

        // The screen that fixes the problem must never be blocked by the rule
        // that sent you to it.
        $this->actingAs($user)->get(route('password.change'))->assertOk();

        // Nor may an operator be trapped in an account they can no longer use.
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_the_policy_is_only_wired_where_it_belongs(): void
    {
        $this->assertContains(
            'password.fresh',
            Route::getRoutes()->getByName('dashboard')->gatherMiddleware()
        );

        foreach (['password.change', 'password.update', 'logout'] as $name) {
            $this->assertNotContains(
                'password.fresh',
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                "{$name} must stay reachable with an expired password."
            );
        }
    }

    public function test_changing_an_expired_password_clears_the_gate_and_is_audited(): void
    {
        $this->enablePasswordExpiry();

        $user = User::factory()->create([
            'password_changed_at' => now()->subDays(400),
            'password' => 'CurrentPassword123',
        ]);

        $tokenBefore = $user->getAttributes()['remember_token'];

        $this->actingAs($user)
            ->post(route('password.update'), [
                'current_password' => 'CurrentPassword123',
                'password' => 'BrandNewPassword123',
                'password_confirmation' => 'BrandNewPassword123',
            ])
            ->assertRedirect(route('login'));

        $user->refresh();

        // The gate reads this column, so it must have been stamped.
        $this->assertFalse($user->passwordMustBeChanged());
        $this->assertLessThan(5, (int) now()->diffInSeconds($user->passwordChangedAt(), true));

        // The old remember-me cookie must stop working, and every session with
        // it — including this one — must end.
        $this->assertNotSame($tokenBefore, $user->getAttributes()['remember_token']);
        $this->assertGuest();

        $row = AuditLog::query()
            ->where('event', SecurityEvent::PasswordChanged->value)
            ->firstOrFail();

        $this->assertSame($user->getKey(), $row->user_id);
        $this->assertSame($user->getKey(), $row->subject_id);

        // And the new credential is what the next sign-in must present.
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'BrandNewPassword123',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_a_wrong_current_password_is_refused_and_changes_nothing(): void
    {
        $this->enablePasswordExpiry();

        $changedAt = now()->subDays(400);

        $user = User::factory()->create([
            'password_changed_at' => $changedAt,
            'password' => 'CurrentPassword123',
        ]);

        $this->actingAs($user)
            ->post(route('password.update'), [
                'current_password' => 'NotThePassword123',
                'password' => 'BrandNewPassword123',
                'password_confirmation' => 'BrandNewPassword123',
            ])
            ->assertSessionHasErrors('current_password');

        $user->refresh();

        // Database timestamps lose sub-second precision on the round trip, so the
        // comparison ignores it: what matters is that the column was untouched.
        $this->assertSame(
            $changedAt->format('Y-m-d H:i:s'),
            $user->passwordChangedAt()->format('Y-m-d H:i:s')
        );
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, AuditLog::query()->count());
    }
}
