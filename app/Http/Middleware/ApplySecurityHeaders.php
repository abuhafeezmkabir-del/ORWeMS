<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response security headers (Phase 3D).
 *
 * Applied globally in bootstrap/app.php so every response is covered —
 * including redirects, error pages and the health probe, which are often the
 * only responses a browser sees before a decision is made.
 *
 *   X-Content-Type-Options: nosniff   Stops a browser re-interpreting an image
 *                                     or download as script or HTML.
 *   X-Frame-Options: SAMEORIGIN       Blocks clickjacking by third-party pages.
 *                                     Kept consistent with `frame-ancestors`.
 *   Referrer-Policy                   Keeps full URLs (which can carry ids) off
 *                                     third-party sites.
 *   Content-Security-Policy           Constrains where scripts, images, frames
 *                                     and styles may come from.
 *   Permissions-Policy                Dismisses device capabilities ORWeMS has
 *                                     no legitimate use for.
 *   X-Permitted-Cross-Domain-Policies Obsolete Flash policy, set to `none`.
 *   Strict-Transport-Security         Only when explicitly enabled (below).
 *
 * CSP design — the one deliberate exception:
 *
 *   `style-src 'self' 'unsafe-inline'` — the Bootstrap/Vite templates use
 *   inline `style="…"` attributes throughout (utility classes and layout
 *   shells). Blocking them would visibly break the UI. This exception covers
 *   STYLES ONLY.
 *
 *   `script-src` stays strict: `'self'` plus the Vite dev origin while
 *   `public/hot` exists. There is NO `'unsafe-inline'` and NO `'unsafe-eval'`
 *   for scripts, so an injected `<script>` element still cannot execute, and
 *   `script-src-attr 'none'` additionally blocks inline event handlers.
 *
 * The Vite dev-server origin is added only while the hot-reload marker file
 * exists, so a production build never carries `localhost:5173`.
 *
 * HTTPS protections are opt-in by configuration and never hard-coded:
 * `ORWEMS_FORCE_HTTPS` (redirect) and `ORWEMS_HSTS_ENABLED` (HSTS header) both
 * default to false so plain-HTTP local development keeps working.
 */
class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Not configurable away: these cost nothing and are correct on every
        // response the application produces.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy());

        if ((bool) config('orwems.security.csp.enabled', true)) {
            // Report-only mode uses the dedicated header rather than a token
            // inside the policy: the enforcing header is omitted entirely, so
            // the browser evaluates and reports the policy without blocking.
            $header = config('orwems.security.csp.report_only', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $this->contentSecurityPolicy($request));
        }

        if ((bool) config('orwems.security.hsts.enabled', false)) {
            $maxAge = (int) config('orwems.security.hsts.max_age', 31536000);
            $preload = config('orwems.security.hsts.preload') ? '; preload' : '';

            $response->headers->set(
                'Strict-Transport-Security',
                sprintf('max-age=%d; includeSubDomains%s', $maxAge, $preload)
            );
        }

        return $response;
    }

    private function permissionsPolicy(): string
    {
        return implode(', ', [
            'accelerometer=()',
            'autoplay=()',
            'camera=()',
            'display-capture=()',
            'encrypted-media=()',
            'fullscreen=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=()',
            'usb=()',
            'xr-spatial-tracking=()',
            'browsing-topics=()',
        ]);
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $dev = $this->viteDevOrigins();

        $script = array_merge(["'self'"], $dev['script']);
        $style = array_merge(["'self'", "'unsafe-inline'"], $dev['style']);
        $connect = array_merge(["'self'"], $dev['connect']);

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            'script-src '.implode(' ', $script),
            // Blocks inline event handlers (onclick=…) even though the script
            // source list already forbids inline <script> elements.
            "script-src-attr 'none'",
            'style-src '.implode(' ', $style),
            'connect-src '.implode(' ', $connect),
            "manifest-src 'self'",
            "worker-src 'self'",
        ];

        if ($request->secure() || (bool) config('orwems.security.force_https', false)) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    /**
     * Origins needed by `npm run dev`. Present only while Vite's hot-reload
     * marker exists, which is how the running dev server announces itself.
     *
     * @return array{script: list<string>, style: list<string>, connect: list<string>}
     */
    private function viteDevOrigins(): array
    {
        $file = base_path('public/hot');

        if (! is_file($file)) {
            return ['script' => [], 'style' => [], 'connect' => []];
        }

        $hot = trim((string) @file_get_contents($file));

        $scheme = parse_url($hot, PHP_URL_SCHEME) ?: 'http';
        $host = parse_url($hot, PHP_URL_HOST) ?: 'localhost';
        $port = parse_url($hot, PHP_URL_PORT);

        $authority = $host.($port !== null ? ':'.$port : '');
        $origin = $scheme.'://'.$authority;

        return [
            'script' => [$origin],
            'style' => [$origin],
            'connect' => [$origin, 'ws://'.$authority, 'wss://'.$authority],
        ];
    }
}