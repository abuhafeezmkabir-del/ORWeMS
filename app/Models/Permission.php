<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A single capability an account may exercise, named `<resource>.<action>`.
 *
 * The `code` is the only value authorization ever compares against. `name` is a
 * display label: it may be reworded at any time without altering what any
 * operator is permitted to do, which is precisely why it must never be used as
 * an authorization key.
 */
class Permission extends Model
{
    use HasFactory;

    /**
     * `is_active` is excluded for the same reason as on Role: retiring a
     * capability is an explicit act, not a form field.
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
     * Responsibilities that confer this capability.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}