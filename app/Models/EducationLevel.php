<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable education level (Phase 6A) — nursery, primary, JSS, SSS,
 * tertiary, vocational, non-formal.
 *
 * Reference data, kept in a table rather than a PHP enum on purpose: the brief
 * asks for "configurable reference data for education levels ... rather than
 * hard-coding business rules in enums". A programme director can add a level
 * the pilot has not needed yet without a schema change, and an enrolment keeps
 * its historical level label even after that level is withdrawn (is_active
 * only stops NEW selection; restrictOnDelete stops a level in use vanishing).
 */
class EducationLevel extends Model
{
    protected $fillable = [
        'code', 'name', 'sort_order', 'description', 'is_active',
    ];

    /**
     * Only levels a caseworker may choose for a NEW enrolment.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(EducationEnrolment::class);
    }
}
