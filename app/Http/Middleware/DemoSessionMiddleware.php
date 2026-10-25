<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DemoSessionMiddleware
 *
 * Protects /demo/* screens with a demo-only session flag.
 *
 * IMPORTANT: this middleware performs NO database queries, reads no production
 * state, and does not touch the `users` table. It exists solely to gate demo
 * screens behind a fixed demonstration credential.
 *
 * Credentials are read from environment variables:
 *   ORWEMS_DEMO_EMAIL        (default: demo@wicov.org)
 *   ORWEMS_DEMO_PASSWORD     (default: demo123)
 *
 * If ORWEMS_DEMO_ENABLED is not set to "true", demo login is rejected and
 * every /demo route returns the "demo not configured" notice instead.
 */
class DemoSessionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $demoEnabled = filter_var(
            config('orwems.demo.enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $demoEnabled) {
            // Demo mode not configured: refuse all demo access with a notice.
            return redirect()->route('demo.login')->with('status', 'demo-not-configured');
        }

        // Logout: clear demo session flag.
        if ($request->routeIs('demo.logout')) {
            $request->session()->forget('demo-logged-in');
            $request->session()->forget('demo-logged-in-at');
            return redirect()->route('demo.login');
        }

        // Allow login page and login POST (credential validation handled by controller).
        if ($request->routeIs('demo.login') || $request->routeIs('demo.login.post')) {
            return $next($request);
        }

        // All other demo routes require a demo session flag.
        if (! $request->session()->get('demo-logged-in')) {
            return redirect()->route('demo.login');
        }

        return $next($request);
    }
}