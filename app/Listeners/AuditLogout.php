<?php

namespace App\Listeners;

use App\Enums\SecurityEvent;
use App\Models\User;
use App\Support\Audit\Auditor;
use Illuminate\Auth\Events\Logout;

/**
 * Records an operator signing out as `logout` (Phase 3D).
 *
 * The actor is taken from the event rather than from the request: by the time
 * this listener runs, the guard has already been cleared, so reading the
 * "current user" here would record nobody.
 *
 * When a disabled account is force-logged-out by EnsureAccountIsActive this
 * fires as well; the preceding `account_disabled` row is what distinguishes a
 * refusal from a voluntary sign-out.
 */
class AuditLogout
{
    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        Auditor::record(
            SecurityEvent::Logout,
            actor: $event->user,
            context: ['guard' => $event->guard],
        );
    }
}