<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Authenticated;

/**
 * Records when an account was last authenticated.
 *
 * Listening to the framework's own `Authenticated` event means every future
 * sign-in path is covered, not only the controller introduced in Phase 3A.
 *
 * `updated_at` is deliberately not touched: signing in does not change the
 * account record, it is a security event in its own right.
 */
class RecordLastLoginAt
{
    public function handle(Authenticated $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        User::withoutTimestamps(function () use ($user) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        });
    }
}
