<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonRelationship extends Model
{
    protected $fillable = [
        'relationship_type_id', 'person_id', 'related_person_id',
        'related_beneficiary_id', 'start_date', 'end_date',
        'is_primary_guardian', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_primary_guardian' => 'boolean',
        ];
    }

    public function relationshipType(): BelongsTo
    {
        return $this->belongsTo(RelationshipType::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id');
    }

    public function relatedBeneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class, 'related_beneficiary_id');
    }
}
