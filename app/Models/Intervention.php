<?php

namespace App\Models;

use App\Enums\InterventionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Intervention extends Model
{
    protected $fillable = [
        'beneficiary_id', 'need_id', 'intervention_type_id', 'programme_id',
        'start_date', 'end_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InterventionStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(Need::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(InterventionType::class, 'intervention_type_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
