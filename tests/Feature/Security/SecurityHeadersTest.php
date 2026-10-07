<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3D — response hardening.
 *
 * The headers are applied by two global middleware (AssignRequestId and
 * ApplySecurityHeaders) so they reach every response, including redirects,
 * 404s and the `/up` health probe. That is asserted here rather than assumed:
 * a header set only on the pages a controller happens to render is a header an
 * attacker can simply avoid.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_baseline_headers_are_present_on_every_response(): void
    {
        foreach (['/', '/up', '/no-such-page', route('login')] as $uri) {
            $response = $this->get($uri);

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
            $this->assertStringContainsString(
                'camera=()',
                (string) $response->headers->get('Permissions-Policy'),
                "Permissions-Policy missing on {$uri}"
            );
        }
    }

    public function test_the_content_security_policy_forbids_inline_scripts(): void
    {
        $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertNotSame('', $csp);

        // 'self' immediately after script-src is the load-bearing assertion:
        // an injected <script src=…> from another origin, and an inline
        // <script> element, are both refused.
        $this->assertMatchesRegularExpression("/script-src 'self'; /", $csp);
        $this->assertStringContainsString("script-src-attr 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);

        // The one documented exception: Bootstrap templates use inline style
        // attributes. It covers styles only and must not leak into scripts.
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function test_the_vite_dev_server_origin_is_not_advertised_in_a_production_build(): void
    {
        $hot = base_path('public/hot');

        if (is_file($hot)) {
            $this->markTestSkipped('A `npm run dev` server is running; public/hot exists.');
        }

        $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('localhost:5173', $csp);
        $this->assertStringNotContainsString('ws://', $csp);
    }

    public function test_the_vite_dev_origin_is_allowed_only_while_the_hot_marker_exists(): void
    {
        $hot = base_path('public/hot');

        if (is_file($hot)) {
            $this->markTestSkipped('A `npm run dev` server is already running.');
        }

        file_put_contents($hot, 'http://localhost:5173');

        try {
            $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

            $this->assertStringContainsString('http://localhost:5173', $csp);
            $this->assertStringContainsString('ws://localhost:5173', $csp);
        } finally {
            @unlink($hot);
        }

        $this->assertFileDoesNotExist($hot);
    }

    public function test_hsts_is_not_sent_unless_it_is_enabled(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeaderMissing('Strict-Transport-Security');
        $this->assertFalse((bool) config('orwems.security.hsts.enabled'));
    }

    public function test_hsts_is_sent_with_a_one_year_max_age_when_enabled(): void
    {
        config()->set('orwems.security.hsts.enabled', true);

        $response = $this->get(route('login'));

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_policy_can_be_switched_to_report_only_for_a_cautious_rollout(): void
    {
        config()->set('orwems.security.csp.report_only', true);

        $response = $this->get(route('login'));

        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_the_content_security_policy_can_be_switched_off_entirely(): void
    {
        config()->set('orwems.security.csp.enabled', false);

        $this->get(route('login'))
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    /* ------------------------------------------------------------------
     | HTTPS / HSTS — configuration-gated, off by default
     ------------------------------------------------------------------ */

    public function test_an_insecure_request_is_not_redirected_by_default(): void
    {
        $this->assertFalse((bool) config('orwems.security.force_https'));

        $this->get('/')->assertOk();
    }

    public function test_an_insecure_request_is_redirected_when_https_is_forced(): void
    {
        config()->set('orwems.security.force_https', true);

        $response = $this->get(route('login'));

        $response->assertStatus(301);

        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('https://', $location);
        $this->assertSame('/login', parse_url($location, PHP_URL_PATH));
    }

    /* ------------------------------------------------------------------
     | Request correlation id
     ------------------------------------------------------------------ */

    public function test_a_request_id_is_echoed_on_every_response(): void
    {
        $id = (string) $this->get(route('login'))->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]{8,64}$/', $id);
    }

    /**
     * The header arrives from outside, so it must not be able to smuggle
     * markup or newlines into a log line or an audit row.
     */
    public function test_an_unsafe_inbound_request_id_is_replaced(): void
    {
        $id = (string) $this->withHeader('X-Request-Id', 'not a valid id!')
            ->get(route('login'))
            ->headers->get('X-Request-Id');

        $this->assertNotSame('not a valid id!', $id);
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $id);
    }
}