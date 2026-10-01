<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 3A — sign in, sign out, and the guest/authenticated boundary.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The first validation error flashed for a field, read from the session.
     *
     * A failed sign-in answers with an HTML redirect, so the message must be
     * read from the flashed session data rather than parsed from the body.
     */
    private function flashedError(string $field): ?string
    {
        return session('errors')?->getBag('default')->first($field);
    }

    public function test_the_login_screen_can_be_rendered_for_guests(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Sign in to your account');
        $response->assertSee('Forgot your password');
        // The form must carry a CSRF token field.
        $response->assertSee('name="_token"', false);
    }

    public function test_guests_are_redirected_from_the_dashboard_to_the_login_screen(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_guest_redirected_from_a_protected_page_is_returned_to_that_page_after_signing_in(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_cannot_authenticate_with_an_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'NotThePassword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_failure_message_does_not_reveal_whether_an_account_exists(): void
    {
        User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        // Correct address, wrong password.
        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'NotThePassword123',
        ])->assertSessionHasErrors('email');
        $messageForWrongPassword = $this->flashedError('email');

        // Address that does not exist at all.
        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'nobody@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertSessionHasErrors('email');
        $messageForUnknownAccount = $this->flashedError('email');

        $this->assertGuest();
        $this->assertSame(trans('auth.failed'), $messageForWrongPassword);
        $this->assertSame(
            $messageForWrongPassword,
            $messageForUnknownAccount,
            'A wrong password and an unknown address must be indistinguishable.'
        );
    }

    public function test_the_session_id_is_regenerated_on_sign_in(): void
    {
        User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->get(route('login'));
        $sessionIdBeforeSignIn = session()->getId();

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertNotSame(
            $sessionIdBeforeSignIn,
            session()->getId(),
            'The session must be regenerated on sign-in to defeat session fixation.'
        );
    }

    public function test_an_authenticated_user_can_reach_the_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Amina Yusuf']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Humanitarian Operations Dashboard');
    }

    public function test_a_user_can_sign_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_a_signed_out_user_can_no_longer_reach_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_sign_out_cannot_be_triggered_by_a_get_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logout')->assertStatus(405);

        $this->assertAuthenticatedAs($user);
    }

    public function test_repeated_failed_attempts_are_throttled(): void
    {
        User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'operator@wichov.test',
                'password' => 'WrongPassword123',
            ]);
        }

        // Even the *correct* password is refused once the key is locked.
        $response = $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNotSame(trans('auth.failed'), $this->flashedError('email'));
    }

    public function test_there_is_no_public_registration_route(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_passwords_are_stored_as_hashes_and_never_in_plaintext(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123']);

        $this->assertNotSame('ValidPassword123', $user->getAttributes()['password']);
        $this->assertTrue(Hash::check('ValidPassword123', $user->getAttributes()['password']));
    }

    public function test_a_failed_sign_in_never_echoes_the_submitted_password(): void
    {
        $response = $this->followingRedirects()->from(route('login'))->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'SuperSecretValue123',
        ]);

        $response->assertOk();
        $response->assertDontSee('SuperSecretValue123');
    }
}
