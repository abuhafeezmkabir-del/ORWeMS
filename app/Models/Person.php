<?php

namespace App\Models;

use App\Enums\Sex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Laravel's pluralizer would resolve Person to "people"; ORWeMS uses the
     * explicit table name "persons".
     */
    protected $table = 'persons';

    protected $fillable = [
        'household_id', 'first_name', 'middle_name', 'last_name',
        'sex', 'date_of_birth', 'phone',
        'is_guardian', 'is_caregiver', 'is_alive', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'date_of_birth' => 'date',
            'is_guardian' => 'boolean',
            'is_caregiver' => 'boolean',
            'is_alive' => 'boolean',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(PersonRelationship::class);
    }

    /**
     * Document metadata attached to this roster record.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
