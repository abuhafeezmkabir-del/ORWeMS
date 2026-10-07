<?php

namespace App\Listeners;

use App\Enums\SecurityEvent;
use App\Models\User;
use App\Support\Audit\Auditor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Str;

/**
 * Records a failed sign-in as `login_failed` (Phase 3D).
 *
 * Two things matter here and both are deliberate:
 *
 *  1. **Only the submitted address is kept.** The event also carries the full
 *     credentials array — including the attempted password — so nothing from
 *     it is forwarded except `email`. Auditor would strip a `password` key
 *     anyway; extracting the one safe field here means the leak is impossible
 *     rather than merely filtered.
 *  2. **The targeted account is linked when one matched.** A wrong password on
 *     a real account and a guess at an address that does not exist both produce
 *     the same generic message to the caller, but the audit trail still records
 *     which accounts are being probed. `subject` is null when no account
 *     matched, which is exactly what makes the row truthful.
 *
 * There is no authenticated actor for a failed attempt, so `user_id` is null.
 */
class AuditLoginFailure
{
    public function handle(Failed $event): void
    {
        $credentials = is_array($event->credentials) ? $event->credentials : [];
        $email = isset($credentials['email']) && is_string($credentials['email'])
            ? Str::lower(trim($credentials['email']))
            : null;

        Auditor::record(
            SecurityEvent::LoginFailed,
            actor: null,
            subject: $event->user instanceof User ? $event->user : null,
            context: [
                'guard' => $event->guard,
                'email' => $email,
                'account_matched' => $event->user instanceof User,
            ],
        );
    }
}