<?php

namespace App\Models;

use App\Enums\ProgrammeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    protected $fillable = ['name', 'code', 'description', 'status'];

    protected function casts(): array
    {
        return ['status' => ProgrammeStatus::class];
    }

    /**
     * Operators scoped to this programme (Phase 3C).
     *
     * The pivot carries `is_active`, so a withdrawn assignment is visible here
     * rather than silently absent — who was once responsible for a programme is
     * part of the record, and `activeProgrammes()` on the User model is what
     * filters to the assignments that still grant access.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'programme_user')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }
}
