<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable health concern category (Phase 6B) — the vocabulary of WHAT
 * kinds of casework-level health record a caseworker may record.
 *
 * Reference data, kept in a table rather than a PHP enum on purpose: the
 * brief calls for "a carefully limited health concern/category, if justified",
 * and a table lets WICHOV withdraw a category (is_active stops NEW selection)
 * without touching the records that already carry it, while restrictOnDelete
 * stops a category in use vanishing. Health data is the most sensitive content
 * ORWeMS holds; the categories here are therefore CASEWORK-level (a wound, a
 * referral in progress, routine monitoring) — never diagnoses, never clinical
 * detail. The vocabulary is the privacy boundary, and it is reviewed as one.
 */
class HealthConcern extends Model
{
    protected $fillable = [
        'code', 'name', 'sort_order', 'description', 'is_active',
    ];

    /**
     * Only concerns a caseworker may choose for a NEW record.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }
}
