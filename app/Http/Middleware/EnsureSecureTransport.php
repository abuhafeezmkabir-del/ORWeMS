<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional HTTP→HTTPS redirect (Phase 3D).
 *
 * Disabled by default: ORWeMS is developed over plain HTTP on
 * http://localhost, and a hard-coded redirect would make local development
 * impossible. It is enabled with a single environment variable on the
 * production deployment, which must be served over HTTPS.
 *
 *   ORWEMS_FORCE_HTTPS=true
 *
 * When enabled, an insecure request is answered with a 301 to the same path on
 * https:// — a redirect rather than an error, so nothing breaks behind a
 * correctly terminated TLS connection and existing links keep working.
 *
 * Deployment note: this reads the request's own scheme. When ORWeMS runs behind
 * a TLS-terminating reverse proxy, the proxy must forward `X-Forwarded-Proto`
 * and the proxy address must be listed in TRUSTED_PROXIES, or every request
 * would still look insecure and redirect in a loop. That is a deployment
 * setting and is deliberately not hard-coded here.
 */
class EnsureSecureTransport
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('orwems.security.force_https', false)) {
            return $next($request);
        }

        if ($request->secure()) {
            return $next($request);
        }

        return redirect()
            ->secure($request->getRequestUri(), 301)
            ->setPrivate();
    }
}