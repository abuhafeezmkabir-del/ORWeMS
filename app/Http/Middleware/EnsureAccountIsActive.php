<?php

namespace App\Http\Middleware;

use App\Enums\SecurityEvent;
use App\Models\User;
use App\Support\Audit\Auditor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses an authenticated session that belongs to a disabled account.
 *
 * Why this exists: Laravel's own `auth` middleware only asks "is someone
 * logged in?". It has no notion of account state, so an operator whose access
 * was revoked would keep working until their session expired. For a system
 * holding children's records, revocation must take effect immediately.
 *
 * It is deliberately narrow: it inspects only the current user, and passes
 * straight through when nobody is signed in.
 *
 * Registered on every authenticated route alongside Laravel's `auth`
 * middleware — see the note in bootstrap/app.php. Because it is a no-op for
 * guests, it can also be applied to mixed routes without harm.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->isActive()) {
            return $next($request);
        }

        // Recorded BEFORE the logout, because afterwards there is no actor
        // left: this row is the only surviving evidence that the account was
        // live a moment ago and was refused rather than never signed in. The
        // Logout listener will add its own row, and the pair reads
        // correctly — account_disabled then logout.
        Auditor::record(
            SecurityEvent::AccountDisabled,
            actor: $user,
            subject: $user,
        );

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors([
                'email' => 'This account has been disabled. Please contact your ORWeMS administrator.',
            ]);
    }
}
