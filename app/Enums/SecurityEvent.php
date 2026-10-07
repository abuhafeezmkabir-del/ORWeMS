<?php

namespace App\Enums;

/**
 * The stable security-event vocabulary ORWeMS writes to `audit_logs`.
 *
 * Event names are machine-readable, lowercase and snake_case, and they are part
 * of the system's contract: a log consumer, a report or an alert rule keys off
 * these strings, so an existing name is never renamed or reused for a different
 * meaning. New events are added as new cases.
 *
 * Cases marked "recorded" are written today by Phase 3D code. Cases marked
 * "reserved" exist so the vocabulary is complete up front and a future phase
 * does not have to invent (or accidentally diverge) a name — they are NOT
 * written by any code yet, and the documentation says so plainly.
 */
enum SecurityEvent: string
{
    /** A credential check succeeded and a session was created. */
    case LoginSuccess = 'login_success';

    /** A credential check failed: unknown address, wrong password, or disabled account. */
    case LoginFailed = 'login_failed';

    /** The sign-in endpoint throttled the caller (LoginRequest / route limiter). */
    case LoginThrottled = 'login_throttled';

    /** A session ended by the operator's own request. */
    case Logout = 'logout';

    /** A password-reset link was requested (and queued for delivery). */
    case PasswordResetRequested = 'password_reset_requested';

    /** A reset link was consumed and the credential was replaced. */
    case PasswordReset = 'password_reset';

    /** An authenticated operator changed their own password. */
    case PasswordChanged = 'password_changed';

    /**
     * A disabled account presented a live session and was refused.
     *
     * Recorded by EnsureAccountIsActive at the moment of refusal, i.e. it means
     * "a revoked account tried to keep working", not "an administrator disabled
     * an account". There is no account-offboarding surface yet; when one is
     * built it will add its own event rather than overload this one.
     */
    case AccountDisabled = 'account_disabled';

    /** A role was newly attached to an account. */
    case RoleAssignment = 'role_assignment';

    /** A programme scope was newly granted or re-activated. */
    case ProgrammeScopeGranted = 'programme_scope_granted';

    /** A programme scope was withdrawn (deactivated, not deleted). */
    case ProgrammeScopeRevoked = 'programme_scope_revoked';

    /** A geographic scope was newly granted or re-activated. */
    case GeographicScopeGranted = 'geographic_scope_granted';

    /** A geographic scope was withdrawn (deactivated, not deleted). */
    case GeographicScopeRevoked = 'geographic_scope_revoked';

    /**
     * RESERVED — not written by any code today.
     *
     * Phase 3B grants permissions to roles through a seeded, version-controlled
     * catalogue; there is no permission-administration surface to audit yet.
     * When one exists it will record this event.
     */
    case PermissionAssignment = 'permission_assignment';

    /**
     * RESERVED — not written by any code today.
     *
     * Placeholder for the completion of second-factor enrolment, which Phase 3D
     * deliberately does not implement (see docs/PHASE3D_SECURITY_HARDENING.md).
     */
    case TwoFactorChanged = 'two_factor_changed';
}
