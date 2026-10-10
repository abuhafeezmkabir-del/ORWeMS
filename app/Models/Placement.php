<?php

namespace App\Models;

use App\Enums\PlacementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * ONE care placement in a child's history (Phase 5).
 *
 * A placement answers "where was this child living, and with whom, from when
 * until when" — the record Phase 2 anticipated and Phase 5 builds. History is
 * written forward only: a row is created, at most amended while it is the
 * active one, and then CLOSED by setting an end date. There is no delete
 * path, and closing is not editing: closure carries its own reason and its
 * own permission (placements.close), so the person who runs the day-to-day
 * case cannot quietly rewrite when a child left.
 *
 * WHAT IS GUARDED HERE, and why it is not left to the controller:
 *
 *  - SINGLE ACTIVE ROW. A child is in one place at a time. The database
 *    refuses a second open row through the unique index over the generated
 *    `active_flag`; this model refuses the common case with a readable
 *    message first, so the caseworker is told to use the Change workflow
 *    rather than seeing a SQL error. Race conditions fall through to the
 *    database constraint, which is the layer that cannot be bypassed.
 *  - STATUS IS DERIVED, NOT CHOSEN. `status` mirrors end_date (open → active,
 *    ended → closed). The MySQL status CHECK refuses drift at the database
 *    level; these guards produce the same rule as validation messages on
 *    every driver, including SQLite, which cannot add the CHECK.
 *  - DATES CANNOT INVERT. end_date before start_date describes a time travel,
 *    not a placement.
 *
 * beneficiary_id, status and end_date are deliberately NOT mass-assignable:
 * the beneficiary is set by the relation that creates the placement
 * ($beneficiary->placements()->create()), and status/end_date are written
 * only by the close/change flows, which also hold the audit call.
 */
class Placement extends Model
{
    protected $fillable = [
        'care_arrangement_type_id',
        'household_id',
        'caregiver_person_id',
        'start_date',
        'placement_reason',
        'closure_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => PlacementStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $placement): void {
            if ($placement->status === null) {
                $placement->status = PlacementStatus::Active;
            }

            if ($placement->end_date !== null
                && $placement->start_date !== null
                && $placement->end_date->lt($placement->start_date)) {
                throw ValidationException::withMessages([
                    'end_date' => 'The end date cannot be before the start date of the placement.',
                ]);
            }

            if (($placement->end_date === null) !== ($placement->status === PlacementStatus::Active)) {
                throw ValidationException::withMessages([
                    'status' => 'A placement without an end date must be active; closing a placement means setting its end date.',
                ]);
            }

            // Friendly first line of defence for the single-active rule. The
            // generated-column unique index remains the hard stop: if two
            // operators race past this check, one write fails at the
            // database, which is exactly the intended outcome.
            if ($placement->end_date === null
                && $placement->beneficiary_id !== null) {
                $conflict = self::query()
                    ->where('beneficiary_id', $placement->beneficiary_id)
                    ->whereNull('end_date')
                    ->when($placement->exists, fn ($query) => $query->whereKeyNot($placement->getKey()))
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'start_date' => 'This beneficiary already has an active placement. Close it first — or use the Change Placement flow, which records both halves together.',
                    ]);
                }
            }
        });
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function careArrangementType(): BelongsTo
    {
        return $this->belongsTo(CareArrangementType::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function caregiverPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'caregiver_person_id');
    }

    /**
     * Is this the placement the child is in right now? Derived from the
     * date pair, which the guards and the database constraint keep in step
     * with `status`.
     */
    public function isActive(): bool
    {
        return $this->end_date === null && $this->status === PlacementStatus::Active;
    }
}