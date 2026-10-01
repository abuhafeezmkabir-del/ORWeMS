<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Email a password reset link.
     *
     * The outcome is deliberately identical whether or not the address belongs
     * to an account, and whether or not mail could be delivered. Reporting the
     * difference would let anyone enumerate valid WICHOV staff addresses. The
     * broker still throttles (config/auth.php) so the endpoint cannot be used to
     * flood anyone's inbox.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        Password::sendResetLink($request->only('email'));

        return back()
            ->with('status', 'If an account exists for that address, a password reset link has been sent.');
    }
}
