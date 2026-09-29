<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

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

    protected static function booted(): void
    {
        // A relationship always points at exactly one related party: either
        // another person or a beneficiary child. Populating both, or neither,
        // would produce a meaningless edge.
        //
        // The same rule is enforced in the database by the
        // person_relationships_related_party_check CHECK constraint on MySQL
        // (added in Phase 2.1). SQLite cannot add a CHECK to an existing table,
        // so this model guard is what keeps behaviour identical on every
        // driver, including the test database, and it produces a readable
        // validation error instead of a driver-specific SQL error.
        static::saving(function (self $relationship) {
            $hasRelatedPerson = ! is_null($relationship->related_person_id);
            $hasRelatedBeneficiary = ! is_null($relationship->related_beneficiary_id);

            if ($hasRelatedPerson === $hasRelatedBeneficiary) {
                throw ValidationException::withMessages([
                    'related_person_id' => $hasRelatedPerson
                        ? 'A relationship cannot reference both a person and a beneficiary.'
                        : 'A relationship must reference either a person or a beneficiary.',
                ]);
            }

            if ($hasRelatedPerson && (int) $relationship->related_person_id === (int) $relationship->person_id) {
                throw ValidationException::withMessages([
                    'related_person_id' => 'A person cannot be related to themselves.',
                ]);
            }
        });
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
