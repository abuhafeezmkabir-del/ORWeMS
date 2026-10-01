<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3A — CSRF protection on the state-changing authentication routes.
 *
 * Laravel skips the CSRF check while the test suite runs, so these tests make
 * the middleware believe it is serving a normal request by unbinding the
 * `testing` environment. That forces the real VerifyCsrfToken check to run.
 */
class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function enableCsrfChecking(): void
    {
        // VerifyCsrfToken::runningUnitTests() is false once the environment is
        // no longer "testing", so the token comparison actually happens.
        $this->app['env'] = 'local';
    }

    public function test_signing_in_without_a_csrf_token_is_rejected(): void
    {
        $this->enableCsrfChecking();

        User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertStatus(419);

        $this->assertGuest();
    }

    public function test_signing_out_without_a_csrf_token_is_rejected(): void
    {
        $this->enableCsrfChecking();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertStatus(419);

        $this->assertAuthenticatedAs($user);
    }

    public function test_signing_in_with_a_csrf_token_succeeds(): void
    {
        $this->enableCsrfChecking();

        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $this->withSession(['_token' => 'a-known-token'])
            ->post(route('login.store'), [
                '_token' => 'a-known-token',
                'email' => 'operator@wichov.test',
                'password' => 'ValidPassword123',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_form_embeds_a_csrf_token(): void
    {
        $response = $this->get(route('login'));

        $response->assertSee('name="_token"', false);
    }
}
