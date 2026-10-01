<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Phase 3A — password reset.
 *
 * Laravel's own password broker does the cryptography and token lifecycle;
 * these tests pin the behaviour ORWeMS depends on: single-use, time-limited,
 * enumeration-safe, and terminating other sessions when it succeeds.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Reset your password');
        $response->assertSee('name="_token"', false);
    }

    public function test_a_reset_link_is_emailed_for_a_real_account(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'operator@wichov.test']);

        $response = $this->post(route('password.email'), ['email' => 'operator@wichov.test']);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'operator@wichov.test']);
    }

    public function test_the_response_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'operator@wichov.test']);

        $this->post(route('password.email'), ['email' => 'operator@wichov.test'])
            ->assertSessionHas('status');
        $messageForKnown = session('status');

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nobody@wichov.test'])
            ->assertSessionHas('status');
        $messageForUnknown = session('status');

        $this->assertSame(
            $messageForKnown,
            $messageForUnknown,
            'The reset response must be identical for known and unknown addresses.'
        );

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_the_reset_screen_renders_with_the_token(): void
    {
        $response = $this->get(route('password.reset', [
            'token' => 'a-sample-token-value',
            'email' => 'operator@wichov.test',
        ]));

        $response->assertOk();
        $response->assertSee('Choose a new password');
        $response->assertSee('a-sample-token-value', false);
        $response->assertSee('operator@wichov.test');
    }

    public function test_a_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'OldPassword123',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('BrandNewPassword456', $user->fresh()->password));
    }

    public function test_the_old_password_stops_working_and_the_new_one_works(): void
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

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'OldPassword123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_new_password_is_stored_as_a_hash(): void
    {
        $user = User::factory()->create(['email' => 'operator@wichov.test']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ]);

        $stored = $user->fresh()->password;

        $this->assertNotSame('BrandNewPassword456', $stored);
        $this->assertTrue(Hash::check('BrandNewPassword456', $stored));
    }

    public function test_a_reset_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create(['email' => 'operator@wichov.test']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('password_reset_tokens', 0);

        $replay = $this->from(route('password.reset', ['token' => $token]))->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'AnotherPassword789',
            'password_confirmation' => 'AnotherPassword789',
        ]);

        $replay->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('BrandNewPassword456', $user->fresh()->password));
    }

    public function test_an_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'operator@wichov.test']);
        $token = Password::broker()->createToken($user);

        // config/auth.php expires reset tokens after 60 minutes.
        DB::table('password_reset_tokens')
            ->where('email', 'operator@wichov.test')
            ->update(['created_at' => now()->subMinutes(61)]);

        $response = $this->from(route('password.reset', ['token' => $token]))->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_a_weak_password_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'operator@wichov.test']);
        $token = Password::broker()->createToken($user);

        $this->from(route('password.reset', ['token' => $token]))->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'operator@wichov.test']);
        $token = Password::broker()->createToken($user);

        $this->from(route('password.reset', ['token' => $token]))->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'SomethingElse456',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_successful_reset_stamps_password_changed_at(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password_changed_at' => null,
        ]);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ])->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->password_changed_at);
    }

    public function test_a_successful_reset_terminates_other_sessions_of_that_account(): void
    {
        // phpunit.xml runs with the in-memory `array` session driver, which has
        // no persistent rows. ORWeMS runs on the `database` driver (see
        // .env.example), so that is what is exercised here.
        config(['session.driver' => 'database']);

        $user = User::factory()->create(['email' => 'operator@wichov.test']);

        // A live authenticated session, e.g. one held by an attacker.
        DB::table('sessions')->insert([
            'id' => 'an-existing-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['id' => 'an-existing-session-id']);
    }

    public function test_a_successful_reset_keeps_working_without_a_persistent_session_store(): void
    {
        // The listener must not assume the database driver and break when
        // sessions are held elsewhere.
        config(['session.driver' => 'array']);

        $user = User::factory()->create(['email' => 'operator@wichov.test']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ])->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->password_changed_at);
    }

    public function test_a_disabled_account_cannot_reset_a_password_to_sign_in(): void
    {
        $user = User::factory()->disabled()->create(['email' => 'operator@wichov.test']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
            'password_confirmation' => 'BrandNewPassword456',
        ])->assertRedirect(route('login'));

        // The password changed, but the disabled status still refuses sign-in.
        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'BrandNewPassword456',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
