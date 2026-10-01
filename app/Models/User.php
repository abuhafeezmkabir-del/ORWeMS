<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * An ORWeMS operator account.
 *
 * WICHOV has no public sign-up: accounts are provisioned by an administrator
 * (Phase 3B), so this model deliberately offers no self-registration path.
 *
 * Identity is the email address, which the users table already constrains to be
 * unique. A username column is therefore not used, and is not added: it would
 * duplicate the identity, introduce a second uniqueness rule, and give staff an
 * identifier to remember that is no safer than the email they already use.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Deliberately minimal. `status` is NOT mass-assignable: an account must
     * never be re-activated or disabled through a request payload. It is set
     * explicitly by administrative code in Phase 3B.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Never serialised. `password` is a hash, but it is still credential
     * material and must not reach a view, a log, or a JSON response.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * `password` => 'hashed' means a plain password assigned to the model is
     * hashed automatically by the framework; callers never hash by hand.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Normalise the email address on write.
     *
     * MySQL's default collation is already case-insensitive, but the test
     * database is SQLite, which is not. Normalising at the model keeps the two
     * engines behaving identically and stops "USER@Example.com" and
     * "user@example.com" ever becoming two accounts.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => is_null($value) ? null : Str::lower(trim($value)),
        );
    }

    /**
     * Whether this account is permitted to hold an authenticated session.
     *
     * Checked in two places: as a condition of the login query, and on every
     * authenticated request, so an account disabled mid-session loses access
     * immediately rather than at its next login.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Whether this account has been disabled.
     */
    public function isDisabled(): bool
    {
        return $this->status === UserStatus::Disabled;
    }
}
