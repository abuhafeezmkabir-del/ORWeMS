<?php

namespace App\Listeners;

use App\Enums\SecurityEvent;
use App\Models\User;
use App\Support\Audit\Auditor;
use Illuminate\Auth\Events\Login;

/**
 * Records a successful sign-in as `login_success` (Phase 3D).
 *
 * Listening to the framework's Login event means every future sign-in path —
 * controller, console, or an API guard added later — is audited, not only the
 * one route that exists today.
 */
class AuditLoginSuccess
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        Auditor::record(
            SecurityEvent::LoginSuccess,
            actor: $event->user,
            context: ['guard' => $event->guard],
            // Deliberately NOT the event's `remember` flag: ORWeMS offers no
            // "remember me" anywhere (LoginRequest), and Auditor would drop the
            // key regardless — `remember` is matched by its forbidden-key rule
            // because `remember_token` is credential material. Passing a field
            // that is guaranteed never to be persisted would only mislead the
            // next reader.
        );
    }
}