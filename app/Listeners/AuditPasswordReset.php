<?php

namespace App\Listeners;

use App\Enums\SecurityEvent;
use App\Models\User;
use App\Support\Audit\Auditor;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Records a consumed password-reset link as `password_reset` (Phase 3D).
 *
 * Separate from LogoutOtherSessionsAfterPasswordReset, which handles the
 * session consequences; this listener is purely the audit record. Neither one
 * touches the credential itself — the broker has already replaced it, and the
 * plaintext existed only inside the request that has long since finished.
 *
 * There is no authenticated actor during a reset (the operator is signed out by
 * definition), so `user_id` is null and the affected account is the subject.
 */
class AuditPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        Auditor::record(
            SecurityEvent::PasswordReset,
            actor: null,
            subject: $event->user,
        );
    }
}