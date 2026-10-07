<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SecurityEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Change the signed-in operator's own password (Phase 3D).
 *
 * This is the screen an expired password must be able to reach, so it is the
 * one authenticated route deliberately NOT behind `password.fresh`. It is still
 * behind `auth` (WHO) and `account.active` (WHETHER) like every other one.
 *
 * Consequences of a change, all of them treated as one atomic act:
 *
 *  1. The new password is hashed by the model cast — never stored, logged or
 *     returned in plaintext.
 *  2. `remember_token` is rotated, so any remember-me cookie already issued
 *     for this account stops working.
 *  3. `password_changed_at` is stamped, which is what the expiry check reads.
 *  4. EVERY session for the account ends, including this one, and the operator
 *     is returned to sign in.
 *
 * (4) is the deliberate design choice. If a password is being changed because
 * it may have been exposed, an attacker's live session must not survive it —
 * and "keep this one, kill the others" requires surgery on session rows that
 * differ by driver. Ending them all is simpler to reason about, strictly
 * safer, and honest: the operator is told plainly that they will be signed
 * out rather than being silently kept logged in on a credential they just
 * replaced.
 */
class AuthenticatedPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.change-password');
    }

    /**
     * @throws ValidationException when the current password is wrong or the new
     *                             one does not meet the strength rule
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => [
                'required',
                'confirmed',
                // Same rule as the reset flow: length is what actually matters,
                // the character rules stop a dictionary word of digits.
                PasswordRule::min(12)->letters()->numbers(),
            ],
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'remember_token' => Str::random(60),
            'password_changed_at' => now(),
        ])->save();

        Auditor::record(
            SecurityEvent::PasswordChanged,
            actor: $user,
            subject: $user,
        );

        $this->endEverySessionFor($request, $user);

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been changed. Please sign in again.');
    }

    /**
     * Destroy every session belonging to this account, including this one.
     *
     * The database driver is handled explicitly because it is the one that
     * persists sessions beyond the request; with any other driver the sessions
     * live in the process or the cache and end when the current request
     * finishes. `invalidate()` then discards the request's own session and
     * `regenerateToken()` replaces the CSRF token, so nothing from the session
     * that was authenticated on the old password survives on the client.
     */
    private function endEverySessionFor(Request $request, User $user): void
    {
        Auth::guard('web')->logout();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table'))
                ->where('user_id', $user->getAuthIdentifier())
                ->delete();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}