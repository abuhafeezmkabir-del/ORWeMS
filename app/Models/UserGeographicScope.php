<?php

namespace App\Models;

use App\Enums\GeographicScopeLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * One geographic area an operator is scoped to.
 *
 * The row names exactly one area at exactly one level. `App\Support\
 * Authorization\ScopeAuthorization` turns a set of these rows into an answer,
 * walking the geography hierarchy at query time so that a state scope covers
 * every LGA, ward and community beneath it without those descendants ever being
 * copied into this table. Copying them would go stale the moment a new ward was
 * added, and would turn one grant into thousands of rows to audit.
 *
 * `is_active` revokes access without erasing the assignment, matching the
 * treatment of users (disabled), roles, permissions and programme scope.
 */
class UserGeographicScope extends Model
{
    /**
     * The five level columns are fillable so the level and its target can be
     * set together. `user_id` and `is_active` are deliberately NOT fillable:
     * ownership comes from the relationship and activation is a security
     * decision, so neither may arrive from a request payload.
     *
     * @var list<string>
     */
    protected $fillable = [
        'level',
        'country_id',
        'state_id',
        'local_government_area_id',
        'ward_id',
        'community_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => GeographicScopeLevel::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The MySQL CHECK constraint
        // (`user_geographic_scopes_level_target_check`) cannot exist on SQLite,
        // which cannot add a CHECK to a table after creation. The same invariant
        // is enforced here so both engines reject the same rows — the approach
        // Phase 2.1 established for person_relationships.
        static::saving(function (self $scope): void {
            $scope->assertExactlyOneLevelTarget();
        });
    }

    /**
     * A scope must name one area, at the level it declares, and no other.
     *
     * The raw attribute is read rather than the cast value so that a corrupt or
     * unrecognised level is reported as a validation failure instead of
     * surfacing as a ValueError from the enum cast.
     */
    private function assertExactlyOneLevelTarget(): void
    {
        $raw = $this->getAttributes()['level'] ?? null;
        $level = is_string($raw) ? GeographicScopeLevel::tryFrom($raw) : null;

        if ($level === null) {
            throw ValidationException::withMessages([
                'level' => 'A geographic scope must declare one of the known levels.',
            ]);
        }

        if ($this->{$level->column()} === null) {
            throw ValidationException::withMessages([
                'level' => 'The declared scope level does not match the geographic area supplied.',
            ]);
        }

        foreach ($level->otherColumns() as $column) {
            if ($this->{$column} !== null) {
                throw ValidationException::withMessages([
                    'level' => 'A geographic scope may name exactly one geographic area.',
                ]);
            }
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function localGovernmentArea(): BelongsTo
    {
        return $this->belongsTo(LocalGovernmentArea::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The primary key of the area this scope names, whichever level it is.
     */
    public function scopeId(): int
    {
        return (int) $this->{$this->level->column()};
    }

    /**
     * Whether this assignment currently confers anything.
     *
     * Deny by default: a withdrawn assignment grants nothing, whatever the row
     * still records.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
