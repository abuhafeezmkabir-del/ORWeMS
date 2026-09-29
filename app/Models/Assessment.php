<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'beneficiary_id', 'programme_id', 'assessment_date',
        'assessment_type', 'assessor_name', 'summary',
    ];

    protected function casts(): array
    {
        return ['assessment_date' => 'date'];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function needs(): HasMany
    {
        return $this->hasMany(Need::class);
    }

    /**
     * Document metadata attached to this assessment (evidence, consent,
     * assessor notes).
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
