<?php

namespace App\Models;

use App\Enums\GeographicScopeLevel;
use App\Enums\ProgrammeStatus;
use App\Enums\UserStatus;
use App\Support\Authorization\ScopeAuthorization;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /*
    |--------------------------------------------------------------------------
    | Access scope (Phase 3C)
    |--------------------------------------------------------------------------
    |
    | Phase 3B answered WHAT this account may do. The methods below answer the
    | separate question of WHERE that authority reaches: which programmes, and
    | which geographic areas.
    |
    | Scope is a FILTER, never a grant. It is consulted only after a permission
    | has already been confirmed, and it can only narrow what that permission
    | reaches — a perfectly matching scope on an account with no relevant
    | permission still reaches nothing. That is what keeps
    | `system_administrator`, which administers accounts and roles, from being
    | able to read a single child's record merely by being scoped everywhere.
    |
    | Nothing here is role-aware and nothing here inspects an account id, an
    | email address or a role code. There is no implicit "administrator sees
    | everything" rule: an operator with national reach is expressed as a
    | country-level geographic scope plus explicit programme assignments, which
    | is visible in the database and revocable like any other grant.
    |
    */

    /**
     * Programmes this account is assigned to, active or not.
     *
     * WithTimestamps() records when each assignment was made; the row is
     * accountability evidence, not a scratch link.
     */
    public function programmes(): BelongsToMany
    {
        return $this->belongsToMany(Programme::class, 'programme_user')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * The programmes this account may actually work in right now.
     *
     * Both filters matter: an assignment that has been withdrawn grants
     * nothing, and neither does an assignment to a programme that is no longer
     * active. Written once here so no caller can forget either.
     */
    public function activeProgrammes(): BelongsToMany
    {
        return $this->programmes()
            ->wherePivot('is_active', true)
            ->where('programmes.status', ProgrammeStatus::Active->value);
    }

    /**
     * Geographic areas this account is scoped to, active or not.
     */
    public function geographicScopes(): HasMany
    {
        return $this->hasMany(UserGeographicScope::class);
    }

    /**
     * The geographic scope assignments that currently confer access.
     */
    public function activeGeographicScopes(): HasMany
    {
        return $this->geographicScopes()->where('is_active', true);
    }

    /**
     * Whether this account may work within a programme.
     */
    public function hasProgrammeAccess(Programme|int $programme): bool
    {
        return ScopeAuthorization::allowsProgramme($this, $programme);
    }

    /**
     * Whether this account may work within a geographic record.
     *
     * Accepts any geography model and resolves its level; a model that is not
     * part of the hierarchy is refused rather than throwing.
     */
    public function hasGeographicAccess(Model $geography): bool
    {
        return ScopeAuthorization::allowsGeography($this, $geography);
    }

    /**
     * Whether this account may reach a record that belongs to both a programme
     * and a place. Both dimensions must be held; either may be omitted only when
     * the record genuinely has just one.
     */
    public function hasScopeFor(?Programme $programme = null, ?Model $geography = null): bool
    {
        return ScopeAuthorization::allowsScope($this, $programme, $geography);
    }

    /**
     * Assign a programme to this account.
     *
     * Idempotent: assigning an already-assigned programme re-activates the
     * existing row rather than adding a second one, so repeated seeding cannot
     * accumulate duplicates. Not mass-assignable and never reachable from a
     * request payload — it exists for administrative code that has already been
     * authorized.
     */
    public function assignProgrammeScope(Programme|int $programme): void
    {
        $programmeId = $programme instanceof Programme ? $programme->getKey() : $programme;

        if ($this->programmes()->whereKey($programmeId)->exists()) {
            $this->programmes()->updateExistingPivot($programmeId, ['is_active' => true]);

            return;
        }

        $this->programmes()->attach($programmeId, ['is_active' => true]);
    }

    /**
     * Withdraw a programme from this account.
     *
     * The row is deactivated, not deleted: who was once responsible for a
     * programme is part of the record of what happened.
     */
    public function revokeProgrammeScope(Programme|int $programme): void
    {
        $programmeId = $programme instanceof Programme ? $programme->getKey() : $programme;

        $this->programmes()->updateExistingPivot($programmeId, ['is_active' => false]);
    }

    /**
     * Scope this account to a geographic area.
     *
     * The level is derived from the model rather than accepted from the caller,
     * so a scope can never be recorded at the wrong level — which would make it
     * match nothing, or worse, match the wrong branch of the hierarchy.
     *
     * Idempotent: re-scoping to an area the account already holds re-activates
     * the existing row. Ownership comes from the relationship and the other four
     * level columns are forced to null, so a single row always names exactly one
     * area.
     */
    public function assignGeographicScope(Model $geography): void
    {
        $level = GeographicScopeLevel::forModel($geography);

        $scope = $this->geographicScopes()->firstOrNew([
            'level' => $level->value,
            $level->column() => $geography->getKey(),
        ]);

        // forceFill rather than fill: user_id is not mass-assignable (ownership
        // is not a form field) and is_active is a security state, so both are
        // set explicitly here and never merged from input.
        $scope->forceFill([
            'user_id' => $this->getKey(),
            'level' => $level->value,
            $level->column() => $geography->getKey(),
            'is_active' => true,
        ]);

        foreach ($level->otherColumns() as $column) {
            $scope->forceFill([$column => null]);
        }

        $scope->save();
    }

    /**
     * Withdraw a geographic area from this account.
     *
     * Deactivates rather than deletes, so the record of which areas an operator
     * could once reach survives a revocation.
     */
    public function revokeGeographicScope(Model $geography): void
    {
        $level = GeographicScopeLevel::forModel($geography);

        $this->geographicScopes()
            ->where('level', $level->value)
            ->where($level->column(), $geography->getKey())
            ->update(['is_active' => false]);
    }
}
