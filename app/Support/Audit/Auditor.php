<?php

namespace App\Support\Audit;

use App\Enums\SecurityEvent;
use App\Http\Middleware\AssignRequestId;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The single writer for ORWeMS audit records (Phase 3D).
 *
 * Nothing else in the application inserts into `audit_logs`. Every call site
 * therefore gets the same three guarantees for free:
 *
 *  1. **Request forensics are attached automatically** — source IP, user agent
 *     and the per-request correlation id, read from the live request when there
 *     is one. Console commands (seeding) simply record nulls.
 *  2. **Credential material is stripped.** Context keys that look like
 *     credentials are dropped, bcrypt-shaped strings are dropped, and arbitrary
 *     objects are never serialised. This runs even where a caller already
 *     extracted safe fields, so a future call site cannot leak by omission.
 *  3. **Fail loudly.** An audit write that fails throws rather than being
 *     swallowed: a silent gap in the security trail is worse than a visible
 *     failure while something important is happening.
 *
 * Actor/subject convention:
 *
 *   user_id       the operator who caused the event (null for a guest, a
 *                 console run, or an attempt against an unknown address)
 *   subject       the object that was changed — for account-authorisation
 *                 events, always the affected account
 */
final class Auditor
{
    /**
     * Context keys that must never be persisted. Matched case-insensitively
     * against the whole key, so `new_password`, `api_token` and `REMEMBER_ME`
     * are all caught by the same rule.
     */
    private const FORBIDDEN_KEYS = '/(password|passwd|pwd|secret|token|credential|authorization|cookie|session|remember|csrf|_token|otp|totp|mfa|2fa|pin|salt|signature|private|api[_-]?key)/i';

    /** Longest single string kept in context. */
    private const MAX_STRING_LENGTH = 500;

    /** Most entries kept in one context payload. */
    private const MAX_ENTRIES = 30;

    /** Deepest nested array inspected. */
    private const MAX_DEPTH = 3;

    /**
     * Append one audit record.
     *
     * @param  SecurityEvent|string  $event  a SecurityEvent case (preferred) or its raw stable name
     * @param  User|null  $actor  who caused the event — pass Auditor::currentActor()
     *                            when the current request's operator should be
     *                            recorded, or null explicitly when there is no
     *                            authenticated actor (a guest, a console run).
     * @param  array<string, mixed>  $context  small, non-sensitive identifiers only
     *
     * @throws \Throwable when the record cannot be written
     */
    public static function record(
        SecurityEvent|string $event,
        ?User $actor = null,
        ?Model $subject = null,
        array $context = [],
    ): AuditLog {
        $request = static::currentRequest();

        return AuditLog::query()->create([
            'user_id' => $actor?->getKey(),
            'event' => $event instanceof SecurityEvent ? $event->value : (string) $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->attributes->get(AssignRequestId::ATTRIBUTE),
            'context' => static::sanitize($context),
        ]);
    }

    /**
     * The account on whose behalf the current request is running, if any.
     *
     * Resolved lazily so this is correct in listeners that fire mid-login and
     * mid-logout, and null in console context where there is no session.
     *
     * Prefers the live request's own operator; falls back to the session
     * guard. The fallback matters only under test: `actingAs()` signs the
     * guard in, but never rebinds the synthetic container request, so
     * `$request->user()` alone would be null even with an actor signed in.
     * Production requests carry both, and the actor is whichever identity
     * resolved — a mismatch there would mean the wrong guard signed in,
     * which is itself a defect worth surfacing rather than papering over.
     */
    public static function currentActor(): ?User
    {
        $user = static::currentRequest()?->user();

        if ($user instanceof User) {
            return $user;
        }

        try {
            $guard = static::authGuard();
        } catch (\Throwable) {
            return null;
        }

        $guarded = $guard?->user();

        return $guarded instanceof User ? $guarded : null;
    }

    /**
     * The session guard, or null outside an HTTP lifecycle.
     */
    private static function authGuard(): ?\Illuminate\Contracts\Auth\Guard
    {
        $container = Container::getInstance();

        if (! $container->bound('auth')) {
            return null;
        }

        /** @var \Illuminate\Auth\AuthManager $auth */
        $auth = $container->make('auth');

        return $auth->guard();
    }

    /**
     * The live HTTP request, or null when running from the console.
     */
    private static function currentRequest(): ?Request
    {
        $container = Container::getInstance();

        if (! $container->bound('request')) {
            return null;
        }

        $request = $container->make('request');

        return $request instanceof Request ? $request : null;
    }

    /**
     * Reduce an arbitrary context array to something safe to persist.
     *
     * @param  array<array-key, mixed>  $context
     *
     * @return array<string, mixed>|null  null when nothing survived, so the
     *                                    column stays NULL rather than '{}'
     */
    private static function sanitize(array $context, int $depth = 0): ?array
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        $clean = [];
        $count = 0;

        foreach ($context as $key => $value) {
            if ($count >= self::MAX_ENTRIES) {
                break;
            }

            $key = (string) $key;

            if (preg_match(self::FORBIDDEN_KEYS, $key) === 1) {
                // The actor-facing vocabulary deliberately includes identifiers
                // that contain a forbidden substring: programme_id,
                // geography_id, role_code. Allow-list the exact shape before
                // the broad credential rule runs, otherwise the grant
                // identifiers would be stripped and the trail unreadable.
                if (! in_array($key, ['programme_id', 'geography_id', 'role_code'], true)) {
                    continue;
                }
            }

            if (is_array($value)) {
                $nested = static::sanitize($value, $depth + 1);

                if ($nested !== null) {
                    $clean[$key] = $nested;
                    $count++;
                }

                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $clean[$key] = $value->format(\DateTimeInterface::ATOM);
                $count++;

                continue;
            }

            // Objects are never serialised: a model or a request could carry an
            // entire credential payload behind innocent-looking properties.
            if (is_object($value)) {
                continue;
            }

            if (is_string($value)) {
                // A bcrypt/argon hash is credential material even though the
                // plaintext it was derived from is gone.
                if (preg_match('/^\$2[ayb]\$|^\$argon2/i', $value) === 1) {
                    continue;
                }

                $clean[$key] = mb_substr($value, 0, self::MAX_STRING_LENGTH);
                $count++;

                continue;
            }

            if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
                $clean[$key] = $value;
                $count++;

                continue;
            }

            // Everything else is dropped rather than guessed at.
        }

        return $clean === [] ? null : $clean;
    }
}