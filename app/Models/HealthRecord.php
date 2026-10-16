<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * ONE casework-level health record in a child's history (Phase 6B).
 *
 * A record answers "what health matter is being tracked for this child, since
 * when, and who still needs to act" — the health counterpart to Phase 6A's
 * EducationEnrolment, but deliberately NARROWER: health is the most sensitive
 * content ORWeMS holds, so this is a casework summary plus follow-up state,
 * never a clinical record. No diagnoses, prescriptions, labs or biometrics.
 *
 * WHAT IS GUARDED HERE, and why it is not left to the controller:
 *
 *  - NO EXACT OPEN DUPLICATE. Several DIFFERENT concerns may be open at once
 *    (a healing wound AND a referral in progress is ordinary casework), so
 *    there is deliberately no single-current invariant like placements or
 *    enrolments have. What is refused is the same child + same concern twice
 *    open: a second open row that adds no information, only a second place
 *    the same issue is tracked. The database refuses it through the unique
 *    index over the generated `open_flag`; this model refuses the common case
 *    with a readable message first. Races fall through to the database, which
 *    is the layer that cannot be bypassed.
 *  - DATES CANNOT INVERT. end_date before record_date is rejected.
 *
 * beneficiary_id, end_date and closure_reason are deliberately NOT
 * mass-assignable: the beneficiary is set by the relation that creates the
 * row ($beneficiary->healthRecords()->create()), and end_date/closure_reason
 * are written only by the close flow, which also holds the audit call. "Open"
 * is end_date IS NULL, derived, never a stored value that could drift.
 */
class HealthRecord extends Model
{
    protected $fillable = [
        'health_concern_id',
        'summary',
        'record_date',
        'follow_up_status',
        'referral_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if ($record->end_date !== null
                && $record->record_date !== null
                && $record->end_date->lt($record->record_date)) {
                throw ValidationException::withMessages([
                    'end_date' => 'The end date cannot be before the record date.',
                ]);
            }

            // Friendly first line of defence for the no-exact-open-duplicate
            // rule. The generated-column unique index remains the hard stop.
            if ($record->end_date === null
                && $record->beneficiary_id !== null
                && $record->health_concern_id !== null) {
                $conflict = self::query()
                    ->where('beneficiary_id', $record->beneficiary_id)
                    ->where('health_concern_id', $record->health_concern_id)
                    ->whereNull('end_date')
                    ->when($record->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'health_concern_id' => 'This beneficiary already has an open record for this concern. Close it first, or record the new information on the existing record.',
                    ]);
                }
            }
        });
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function healthConcern(): BelongsTo
    {
        return $this->belongsTo(HealthConcern::class);
    }

    /**
     * Is this record still open? Derived from the date pair (there is no
     * stored status to drift), which the model guard and the database unique
     * index keep duplicate-free.
     */
    public function isOpen(): bool
    {
        return $this->end_date === null;
    }

    /**
     * Is follow-up still outstanding on this record?
     */
    public function needsFollowUp(): bool
    {
        return $this->follow_up_status === 'pending';
    }
}
