<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * ONE education enrolment in a child's history (Phase 6A).
 *
 * An enrolment answers "where was this child studying, at what level, from when
 * until when" — the education counterpart to Phase 5's Placement. History is
 * written forward only: a row is created, at most amended while it is the
 * current one, and then CLOSED by setting an end date. There is no delete path,
 * and closing is not editing: closure carries its own reason and its own
 * permission (education.close), so whoever runs the day-to-day case cannot
 * quietly rewrite when a child left a school.
 *
 * WHAT IS GUARDED HERE, and why it is not left to the controller:
 *
 *  - ONE CURRENT ENROLMENT. A child is enrolled in one place at a time. The
 *    database refuses a second current row through the unique index over the
 *    generated `active_flag`; this model refuses the common case with a
 *    readable message first, so the caseworker is told to use the Change
 *    workflow rather than seeing a SQL error. Race conditions fall through to
 *    the database constraint, which is the layer that cannot be bypassed.
 *  - DATES CANNOT INVERT. end_date before start_date describes a time travel,
 *    not an enrolment.
 *
 * beneficiary_id, end_date and closure_reason are deliberately NOT
 * mass-assignable: the beneficiary is set by the relation that creates the row
 * ($beneficiary->enrolments()->create()), and end_date/closure_reason are
 * written only by the close/change flows, which also hold the audit call. This
 * is why there is no status ENUM either — "current" is end_date IS NULL,
 * derived, never a stored value that could drift.
 */
class EducationEnrolment extends Model
{
    protected $fillable = [
        'education_level_id',
        'institution_id',
        'institution_name',
        'class_or_year',
        'start_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $enrolment): void {
            if ($enrolment->end_date !== null
                && $enrolment->start_date !== null
                && $enrolment->end_date->lt($enrolment->start_date)) {
                throw ValidationException::withMessages([
                    'end_date' => 'The end date cannot be before the start date of the enrolment.',
                ]);
            }

            // Friendly first line of defence for the one-current-enrolment
            // rule. The generated-column unique index remains the hard stop: if
            // two operators race past this check, one write fails at the
            // database, which is exactly the intended outcome.
            if ($enrolment->end_date === null
                && $enrolment->beneficiary_id !== null) {
                $conflict = self::query()
                    ->where('beneficiary_id', $enrolment->beneficiary_id)
                    ->whereNull('end_date')
                    ->when($enrolment->exists, fn ($query) => $query->whereKeyNot($enrolment->getKey()))
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'start_date' => 'This beneficiary already has a current enrolment. Close it first — or use the Change Enrolment flow, which records both halves together.',
                    ]);
                }
            }
        });
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Is this the enrolment the child is in right now? Derived from the date
     * pair (there is no stored status to drift), which the model guard and the
     * database unique index keep singular.
     */
    public function isCurrent(): bool
    {
        return $this->end_date === null;
    }
}
