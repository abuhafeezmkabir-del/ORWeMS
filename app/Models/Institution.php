<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An OPTIONAL structured institution reference (Phase 6A) — a school a
 * programme works with.
 *
 * This table is deliberately UNSEEDED and there is no management screen for it
 * yet: institutions are specific real-world places, and inventing them in a
 * seeder would fabricate programme data. The enrolment always records the
 * school by NAME (education_enrolments.institution_name); this row is
 * enrichment a later phase can populate and manage. Keeping the link optional
 * and nullOnDelete means a child's history never depends on it.
 */
class Institution extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_active',
    ];

    /**
     * Only institutions a caseworker may link to a NEW enrolment.
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
