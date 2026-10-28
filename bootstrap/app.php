<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\DemoSessionMiddleware;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsFresh;
use App\Http\Middleware\EnsureSecureTransport;
use App\Http\Middleware\EnsureUserHasPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/auth.php'));
            require base_path('routes/demo.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust Render's HTTPS reverse proxy so Laravel correctly recognizes
        // HTTPS requests forwarded over HTTP from the container. Render
        // terminates TLS at the edge and forwards X-Forwarded-Proto to the
        // container. Without this trust, every request looks like plain HTTP
        // to Laravel, causing asset URLs (via @vite()) to be generated as
        // http:// and blocked by browsers as mixed content on an https:// page.
        //
        // at: '*' trusts all proxies because Render's proxy addresses
        // are not published in a fixed range. headers: X-Forwarded-Proto only
        // allows the scheme header to influence the request; host and client
        // IP are not overridable via forwarded headers.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_PROTO,
        );

        // Authentication routes.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        // Phase 3D — cross-cutting request/response hardening.
        //
        // Appended to the GLOBAL stack rather than to the `web` group, so the
        // headers reach every response including redirects, 404s, CSRF
        // rejections and the `/up` health probe. None of the three touches the
        // session, the database or the authenticated user, so running before
        // `StartSession` is safe by construction.
        //
        //   AssignRequestId      one correlation id per request, echoed back as
        //                        X-Request-Id and stamped onto audit rows
        //   EnsureSecureTransport  optional HTTP->HTTPS redirect; off unless
        //                        ORWEMS_FORCE_HTTPS=true, so local HTTP works
        //   ApplySecurityHeaders  CSP, frame, referrer, type and permissions
        //                        headers on every response; HSTS only when
        //                        ORWEMS_HSTS_ENABLED=true
        //
        // Order matters only in that AssignRequestId must run first: the audit
        // rows written while a later middleware is running need its id.
        $middleware->append([
            AssignRequestId::class,
            EnsureSecureTransport::class,
            ApplySecurityHeaders::class,
        ]);

        // Applied explicitly alongside `auth` on authenticated routes.
        //
        // It is NOT added with appendToGroup(). Two traps make group injection
        // unsafe here:
        //
        // 1. MiddlewareNameResolver::resolve() checks middleware *groups*
        //    before aliases, so a group named `auth` silently shadows the
        //    `auth` alias and Authenticate stops running altogether.
        // 2. In this Laravel 12 skeleton, appendToGroup() updates the HTTP
        //    Kernel's groups but not the Router's, which is what actually
        //    resolves route middleware names — the middleware is then never
        //    invoked at all.
        //
        // An explicit alias on the route is verifiable and cannot be silently
        // dropped. Phase 3B wraps every authenticated route in one group.
        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
            'permission' => EnsureUserHasPermission::class,
            'demo.session' => DemoSessionMiddleware::class,
            'password.fresh' => EnsurePasswordIsFresh::class,

            // Phase 3D — password-age gate. Explicit alias on the route for the
            // same reason as the two above. It is a no-op unless password
            // expiry is enabled in config/orwems.php, and it is deliberately
            // NOT applied to the change-password routes: the screen that fixes
            // an expired password must never be blocked by it.
            'password.fresh' => EnsurePasswordIsFresh::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
