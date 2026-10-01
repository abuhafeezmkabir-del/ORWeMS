<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Replace a forgotten password.
     *
     * The work is done by Laravel's password broker: the token is compared in
     * constant time, must be younger than `auth.passwords.users.expire`
     * (60 minutes), and is deleted the moment it is used, so a link is
     * single-use. The new password is hashed by the model cast — it is never
     * stored, logged or returned in plaintext.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                // Length is the requirement that actually matters; the character
                // rules keep staff from choosing a dictionary word of digits.
                PasswordRule::min(12)->letters()->numbers(),
            ],
        ]);

        $resetUser = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resetUser) {
                $user->forceFill([
                    // Rotating the remember token invalidates any "remember me"
                    // cookie already issued for this account.
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $resetUser = $user;
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            // Laravel 12's PasswordBroker performs the token deletion but does
            // NOT dispatch Illuminate\Auth\Events\PasswordReset, so ORWeMS
            // raises it here. Listeners stamp password_changed_at and destroy
            // the account's remaining sessions.
            event(new PasswordReset($resetUser));

            return redirect()
                ->route('login')
                ->with('status', 'Your password has been reset. You may now sign in.');
        }

        return back()
            ->withErrors(['email' => 'That password reset link is no longer valid. Please request a new one.']);
    }
}
