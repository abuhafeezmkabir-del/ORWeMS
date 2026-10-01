<?php

use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Authentication routes.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
