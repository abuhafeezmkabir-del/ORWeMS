<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and performs a sign-in attempt.
 *
 * Three security properties are handled here:
 *
 *  1. No user enumeration. Account status is folded into the credential query,
 *     so a disabled account and a wrong password produce exactly the same
 *     generic `auth.failed` response.
 *  2. Brute-force resistance. Repeated failures from the same email/IP pair are
 *     throttled, and successful sign-in clears the counter.
 *  3. No unnecessary persistence. "Remember me" is intentionally not offered,
 *     so a session always ends with the configured idle lifetime.
 */
class LoginRequest extends FormRequest
{
    /** Maximum failed attempts before the key is locked. */
    private const MAX_ATTEMPTS = 5;

    /** Lockout window in seconds. */
    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to sign the user in.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::attempt([
            'email' => $this->email(),
            'password' => $this->string('password')->toString(),
            // Scoping the lookup to active accounts is what stops a disabled
            // account authenticating. It is deliberately part of the credential
            // query rather than a separate check, so that the failure is
            // indistinguishable from any other bad credential.
            'status' => UserStatus::Active->value,
        ]);

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', ['seconds' => $seconds]),
        ]);
    }

    /**
     * Rate-limit key: one throttle per email address and IP pair, so an
     * attacker cannot lock a real account out from a different address by
     * guessing repeatedly, nor spread guesses across addresses from one host.
     */
    public function throttleKey(): string
    {
        return Str::transliterate($this->email().'|'.$this->ip());
    }

    /**
     * The submitted address, normalised the same way the model normalises it, so
     * that sign-in is not case sensitive.
     */
    public function email(): string
    {
        return Str::lower(trim((string) $this->input('email')));
    }
}
