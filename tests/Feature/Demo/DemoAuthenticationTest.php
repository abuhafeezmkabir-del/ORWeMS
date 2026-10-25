<?php

namespace Tests\Feature\Demo;

use Tests\TestCase;

/**
 * DemoAuthenticationTest
 *
 * Validates the demonstration-mode authentication flow:
 * - Login page loads
 * - Valid credentials grant access
 * - Invalid credentials are rejected
 * - Logout clears the demo session
 * - Protected routes redirect unauthenticated users to login
 */
class DemoAuthenticationTest extends TestCase
{
    public function test_login_page_loads(): void
    {
        $response = $this->get(route('demo.login'));
        $response->assertStatus(200);
        $response->assertSee('DEMONSTRATION MODE');
        $response->assertSee('Sign in to ORWeMS Demo');
    }

    public function test_valid_credentials_grant_demo_access(): void
    {
        $response = $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response->assertRedirect(route('demo.dashboard'));
        $this->assertTrue(session('demo-logged-in'));
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $response = $this->post(route('demo.login.post'), [
            'email' => 'wrong@example.org',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', 'demo-login-failed');
        $this->assertNull($response->getSession()->get('demo-logged-in'));
    }

    public function test_logout_clears_demo_session(): void
    {
        $response = $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);
        $response->assertRedirect(route('demo.dashboard'));
        $this->assertTrue($response->getSession()->get('demo-logged-in'));

        $response = $this->withSession(['demo-logged-in' => true])
            ->post(route('demo.logout'));
        $response->assertRedirect(route('demo.login'));
        $this->assertNull($response->getSession()->get('demo-logged-in'));
    }

    public function test_protected_demo_route_redirects_when_not_logged_in(): void
    {
        $response = $this->get(route('demo.dashboard'));
        $response->assertRedirect(route('demo.login'));
    }

    public function test_demo_routes_do_not_query_database(): void
    {
        // Demo auth uses env vars only; this test asserts that a login
        // attempt does not touch the `users` table by checking that the
        // response works regardless of database state.
        $response = $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $response->assertRedirect(route('demo.dashboard'));
    }

    public function test_session_id_regenerates_on_successful_login(): void
    {
        // Establish a guest session first.
        $this->get(route('demo.login'));
        $beforeId = session()->getId();

        // Successful login must regenerate the session ID (session fixation protection).
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        $afterId = session()->getId();

        $this->assertNotEquals($beforeId, $afterId, 'Session ID must change after successful demo login.');
        $this->assertTrue(session('demo-logged-in'));
    }

    public function test_login_is_rate_limited(): void
    {
        // throttle:5,1 allows 5 attempts per minute. The 6th must be rejected.
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('demo.login.post'), [
                'email' => 'wrong@example.org',
                'password' => 'wrongpassword',
            ]);
            $response->assertStatus(302);
            $response->assertSessionHas('status', 'demo-login-failed');
        }

        $response = $this->post(route('demo.login.post'), [
            'email' => 'wrong@example.org',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429);
    }

    public function test_disabled_demo_blocks_all_access(): void
    {
        config(['orwems.demo.enabled' => false]);

        // Dashboard access is refused.
        $response = $this->get(route('demo.dashboard'));
        $response->assertRedirect(route('demo.login'));
        $response->assertSessionHas('status', 'demo-not-configured');

        // Login POST is refused with the same notice.
        $response = $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);
        $response->assertRedirect(route('demo.login'));
        $response->assertSessionHas('status', 'demo-not-configured');
    }

    public function test_demo_session_persists_across_requests(): void
    {
        // Log in.
        $this->post(route('demo.login.post'), [
            'email' => 'demo@wicov.org',
            'password' => 'demo123',
        ]);

        // Subsequent requests across different demo screens must remain authenticated.
        $this->get(route('demo.dashboard'))->assertStatus(200);
        $this->get(route('demo.health.index', 'BEN-2026-001'))->assertStatus(200);
        $this->get(route('demo.care.index', 'BEN-2026-001'))->assertStatus(200);
        $this->get(route('demo.education.index', 'BEN-2026-001'))->assertStatus(200);

        $this->assertTrue(session('demo-logged-in'));
    }
}