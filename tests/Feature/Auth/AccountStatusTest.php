<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3A — account status.
 *
 * A disabled account must be refused at sign-in *and* lose any session it
 * already holds, because revocation for a children's records system cannot be
 * allowed to wait for a session to expire.
 */
class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_account_can_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
            'status' => UserStatus::Active,
        ]);

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_disabled_account_cannot_authenticate(): void
    {
        User::factory()->disabled()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_disabled_account_is_treated_exactly_like_an_unknown_one(): void
    {
        User::factory()->disabled()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertSessionHasErrors('email');
        $messageForDisabled = session('errors')?->getBag('default')->first('email');

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'nobody@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertSessionHasErrors('email');
        $messageForUnknown = session('errors')?->getBag('default')->first('email');

        $this->assertGuest();
        $this->assertSame(trans('auth.failed'), $messageForDisabled);
        $this->assertSame(
            $messageForDisabled,
            $messageForUnknown,
            'A disabled account must not be distinguishable from an unknown one.'
        );
    }

    public function test_a_disabled_account_cannot_reach_the_dashboard(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_disabling_an_account_terminates_its_live_session(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        // An administrator revokes access while the operator is signed in.
        $user->forceFill(['status' => UserStatus::Disabled])->save();

        // Each real request builds a fresh guard, but the test container keeps
        // one instance (and the user model it already resolved) alive across
        // simulated requests. Dropping it restores production behaviour.
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_disabled_account_can_be_reactivated_by_an_explicit_assignment(): void
    {
        $user = User::factory()->disabled()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->assertTrue($user->isDisabled());
        $this->assertFalse($user->isActive());

        // Status is never mass-assignable, so reactivation is always explicit.
        $user->forceFill(['status' => UserStatus::Active])->save();

        $this->assertTrue($user->fresh()->isActive());

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_account_status_cannot_be_changed_by_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Operator',
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
            'status' => UserStatus::Disabled,
        ]);

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => UserStatus::Active->value,
        ]);
    }

    public function test_the_status_column_is_cast_to_the_user_status_enum(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $this->assertInstanceOf(UserStatus::class, $user->fresh()->status);
        $this->assertSame('active', $user->fresh()->status->value);
    }
}
