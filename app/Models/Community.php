<?php

namespace App\Models;

use App\Support\Geography\GeographicHierarchy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Community extends Model
{
    protected $fillable = [
        'ward_id', 'local_government_area_id', 'name', 'code', 'type_label', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // communities stores both ward_id and local_government_area_id so that
        // settlements without a formal ward can still be recorded, and so LGA
        // reporting needs no join. Foreign keys prove each level exists, but
        // cannot prove they belong to the same branch of the hierarchy, so the
        // agreement is checked on save (see App\Support\Geography).
        static::saving(function (self $community) {
            GeographicHierarchy::assertCommunityIsWithinHierarchy(
                $community->ward_id !== null ? (int) $community->ward_id : null,
                $community->local_government_area_id !== null ? (int) $community->local_government_area_id : null,
            );
        });
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function localGovernmentArea(): BelongsTo
    {
        return $this->belongsTo(LocalGovernmentArea::class);
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }
}
