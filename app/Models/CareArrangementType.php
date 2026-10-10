<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of care arrangement a placement can take (kinship care, foster care,
 * residential care, ...).
 *
 * Reference data, not configuration: rows are seeded by CareArrangementTypeSeeder
 * and are expected to grow under programme governance. `is_active` withdraws a
 * kind from NEW selection without touching placements that already carry it —
 * historical records must keep their label forever, so there is no delete
 * operation and placements.restrictOnDelete refuses removing a kind in use.
 */
class CareArrangementType extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    /**
     * Arrangement kinds an operator may choose from when recording a placement.
     *
     * Withdrawn kinds stay visible on existing rows but appear in no form.
     *
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}