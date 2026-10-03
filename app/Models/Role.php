<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named responsibility an ORWeMS account may hold.
 *
 * A role answers WHAT. It never answers WHERE: no state, LGA, ward, community
 * or programme column exists on this model, and none may be added. Those
 * describe the area a responsibility applies in, which is Phase 3C and is held
 * in separate tables so that one role can be granted across many areas.
 */
class Role extends Model
{
    use HasFactory;

    /**
     * `is_active` is deliberately NOT mass-assignable, mirroring `User::status`
     * in Phase 3A. Withdrawing a role from every holder is a security decision
     * and must never be a side effect of a request payload.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Accounts holding this responsibility.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Capabilities this responsibility confers.
     *
     * A grant is meaningful only while both ends are active, so the deactivation
     * of a role or a permission withdraws the privilege without touching this
     * pivot — the grant stays on record and simply stops granting.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    /**
     * Whether this role currently confers any privilege.
     *
     * Deny by default: an inactive role grants nothing, whatever its pivot rows
     * still say.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}