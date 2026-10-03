<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * An ORWeMS operator account.
 *
 * WICHOV has no public sign-up: accounts are provisioned by an administrator,
 * so this model deliberately offers no self-registration path.
 *
 * Identity is the email address, which the users table already constrains to be
 * unique. A username column is therefore not used, and is not added: it would
 * duplicate the identity, introduce a second uniqueness rule, and give staff an
 * identifier to remember that is no safer than the email they already use.
 *
 * Authorization (Phase 3B) is defined here and nowhere else: `hasPermission()`
 * is the single place that decides what this account may do. Controllers, views
 * and middleware all ask that one question rather than re-deriving the answer.
 *
 * `Authorizable` — and therefore `$user->can()`, `can:` middleware and the
 * `@can` Blade directive — already comes from Illuminate\Foundation\Auth\User.
 * It is wired to the permission vocabulary in App\Providers\AppServiceProvider,
 * so those idioms resolve through the same single source of truth.
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

    /*
    |--------------------------------------------------------------------------
    | Phase 3B — authorization
    |--------------------------------------------------------------------------
    |
    | Everything below answers ONE question: may this account exercise a named
    | capability? The answer is always derived, never stored on the users table.
    | A role column on `users` could not express many-to-many assignment, could
    | not be granted or withdrawn per capability, and would leave no record of
    | which of several responsibilities a person actually held.
    |
    */

    /**
     * Responsibilities held by this account.
     *
     * WithTimestamps() records when each assignment was made: the table is
     * accountability evidence, not a scratch link.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Every capability this account can currently exercise.
     *
     * Resolved through the role assignments, ignoring both inactive roles and
     * inactive permissions: withdrawing either one must revoke the privilege
     * immediately, without anyone having to edit a pivot row.
     *
     * De-duplicated. Two roles commonly grant the same capability — a programme
     * manager and an auditor both hold `beneficiaries.view` — and the join would
     * otherwise return it once per role. This method answers "which capabilities
     * does this account hold", which is a set, so holding one through two roles
     * is still holding it once. Callers that count permissions, or show them to
     * an administrator, depend on that.
     *
     * Deny by default is structural here, not conditional. The set is built by
     * joining real rows, so a capability nobody was granted simply is not in
     * it — there is no fallback branch that could allow by omission.
     */
    public function permissionCodes(): Collection
    {
        return Permission::query()
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->getKey())
            ->where('permissions.is_active', true)
            ->where('roles.is_active', true)
            ->distinct()
            ->orderBy('permissions.code')
            ->pluck('permissions.code');
    }

    /**
     * Whether this account holds a named capability.
     *
     * Takes the permission CODE, never the display name. An unknown code is not
     * an error and is never treated as "probably allowed" — it returns false,
     * so a typo in a route declaration fails closed and visibly rather than
     * silently permitting access.
     */
    public function hasPermission(string $code): bool
    {
        return $this->permissionCodes()->contains($code);
    }

    /**
     * Whether this account holds a named responsibility.
     */
    public function hasRole(string $code): bool
    {
        return $this->roles()
            ->where('roles.code', $code)
            ->exists();
    }

    /**
     * Grant a responsibility.
     *
     * Idempotent (syncWithoutDetaching) and NOT exposed as a mass-assignable
     * attribute, so it can only be reached from explicit administrative code
     * that has already been authorized. This is what keeps an ordinary account
     * from handing itself a role through a request payload.
     */
    public function assignRole(Role $role): void
    {
        $this->roles()->syncWithoutDetaching([$role->getKey()]);
    }
}
