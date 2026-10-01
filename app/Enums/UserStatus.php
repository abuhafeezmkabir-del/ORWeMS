<?php

namespace App\Enums;

/**
 * Lifecycle of an ORWeMS operator account.
 *
 * Phase 3A deliberately models the minimum two states needed to revoke access
 * safely. It is stored as a string column cast to this enum (rather than a
 * boolean) so that further states — for example "suspended" for a temporary
 * investigation hold — can be introduced later without a schema change, and so
 * that the value stays consistent with the other ORWeMS status columns.
 */
enum UserStatus: string
{
    /** May authenticate and use the authenticated area of ORWeMS. */
    case Active = 'active';

    /**
     * May not authenticate. Used when an account is offboarded or access is
     * revoked. Disabled accounts are refused at login and, if one already holds
     * a session, it is terminated on the next request.
     */
    case Disabled = 'disabled';
}
