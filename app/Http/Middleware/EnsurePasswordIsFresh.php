<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses an authenticated session whose password is past its configured
 * age — and only when that policy is switched on (Phase 3D foundation).
 *
 * CONFIGURATION, NOT A HARDCODED RULE
 *
 *   orwems.security.password_expiry.enabled  default false — a no-op until
 *                                            WICHOV adopts a policy
 *   orwems.security.password_expiry.days     default 180, the only place the
 *                                            period is defined
 *
 * With expiry disabled this middleware does nothing at all, so existing
 * authentication behaviour is untouched by default.
 *
 * WHEN ENABLED
 *
 *  - A null `password_changed_at` is grandfathered, not expired. The column is
 *    populated from the first reset or change; treating "unknown" as "expired"
 *    would lock every pre-existing account out the moment the flag is flipped,
 *    with no way back in. New credentials always stamp the column.
 *  - The redirect lands on the password-change flow, which deliberately does
 *    NOT carry this middleware (see routes/auth.php), so an operator whose
 *    password has expired can always reach the only screen that can fix it.
 *  - `logout` is exempt for the same reason: an operator must be able to sign
 *    out of an account they can no longer use.
 *
 * Alias: `password.fresh`, declared in bootstrap/app.php. Applied as an
 * explicit route alias alongside `auth` and `account.active`, for the reason
 * documented there about group injection in this skeleton.
 */
class EnsurePasswordIsFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('orwems.security.password_expiry.enabled', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        // The escape hatches: the screen that fixes the problem, and signing out.
        if ($request->routeIs('password.change', 'password.update', 'logout')) {
            return $next($request);
        }

        if (! $user->passwordMustBeChanged()) {
            return $next($request);
        }

        return redirect()
            ->route('password.change')
            ->withErrors([
                'password' => 'Your password has expired. Choose a new one to continue.',
            ]);
    }
}