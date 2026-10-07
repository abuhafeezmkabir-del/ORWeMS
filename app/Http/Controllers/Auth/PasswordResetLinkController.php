<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SecurityEvent;
use App\Http\Controllers\Controller;
use App\Support\Audit\Auditor;
use Illuminate\Auth\Passwords\PasswordBroker;
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

        $email = mb_strtolower(trim($request->string('email')->toString()));

        // Whether an account matched is recorded for the trail and is
        // deliberately NOT reflected in the response: the reply below is
        // identical for a real address, an unknown one and a throttled one,
        // which is what stops this endpoint enumerating WICHOV staff
        // addresses. The distinction lives in the audit table, not in the
        // answer the caller sees.
        $matched = Password::sendResetLink($request->only('email'))
            === PasswordBroker::RESET_LINK_SENT;

        Auditor::record(SecurityEvent::PasswordResetRequested, context: [
            'email' => $email,
            'account_matched' => $matched,
        ]);

        return back()
            ->with('status', 'If an account exists for that address, a password reset link has been sent.');
    }
}
