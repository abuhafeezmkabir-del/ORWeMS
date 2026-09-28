<?php

namespace App\Models;

use App\Enums\NeedStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Need extends Model
{
    use HasFactory;
    protected $fillable = [
        'assessment_id', 'beneficiary_id', 'need_category_id',
        'title', 'description', 'priority', 'status',
    ];

    protected function casts(): array
    {
        return ['status' => NeedStatus::class];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NeedCategory::class, 'need_category_id');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }
}
