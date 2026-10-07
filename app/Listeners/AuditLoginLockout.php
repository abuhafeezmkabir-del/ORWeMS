<?php

namespace App\Listeners;

use App\Enums\SecurityEvent;
use App\Support\Audit\Auditor;
use Illuminate\Auth\Events\Lockout;

/**
 * Records that the sign-in endpoint throttled a caller (`login_throttled`).
 *
 * Rate limiting is a security control, and a control firing is exactly the kind
 * of fact an investigation needs: several `login_throttled` rows from one
 * address in an evening is a brute-force attempt, and it is invisible if the
 * limiter silently drops requests.
 *
 * Only the address that triggered the lockout is kept; there is no account to
 * link, because throttling is keyed on the email/IP pair before any credential
 * is validated.
 */
class AuditLoginLockout
{
    public function handle(Lockout $event): void
    {
        Auditor::record(
            SecurityEvent::LoginThrottled,
            actor: null,
            context: [
                'email' => $event->request->input('email'),
                'ip' => $event->request->ip(),
            ],
        );
    }
}