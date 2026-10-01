<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;

/**
 * Reacts to a completed password reset.
 *
 * A reset is a security event, so two things must happen that the broker does
 * not do by itself:
 *
 *  1. `password_changed_at` is stamped, so account review can tell when a
 *     credential last changed.
 *  2. Every other session belonging to the account is destroyed. If a password
 *     was changed because it may have been exposed, the attacker's existing
 *     authenticated session must not survive it.
 */
class LogoutOtherSessionsAfterPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        $user->forceFill(['password_changed_at' => now()])->saveQuietly();

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table'))
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
    }
}
