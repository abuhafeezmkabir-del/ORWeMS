<?php

namespace App\Models;

use App\Enums\BeneficiaryStatus;
use App\Enums\DateAccuracy;
use App\Enums\Sex;
use App\Support\Authorization\ScopeAuthorization;
use App\Support\Geography\GeographicHierarchy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Beneficiary extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'beneficiary_ref', 'first_name', 'middle_name', 'last_name',
        'sex', 'date_of_birth', 'date_of_birth_accuracy',
        'nationality_country_id', 'country_id', 'state_id',
        'local_government_area_id', 'ward_id', 'community_id',
        'household_id', 'person_id', 'programme_id',
        'registration_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'date_of_birth' => 'date',
            'date_of_birth_accuracy' => DateAccuracy::class,
            'status' => BeneficiaryStatus::class,
            'registration_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // The reference column is NOT NULL + UNIQUE, so a provisional unique
        // value is written on insert and then replaced with the stable
        // reference derived from the primary key. The final reference is
        // unique, stable and free of personal characteristics.
        static::creating(function (Beneficiary $beneficiary) {
            if (empty($beneficiary->beneficiary_ref)) {
                $beneficiary->beneficiary_ref = 'PENDING-'.Str::ulid();
            }
        });

        static::created(function (Beneficiary $beneficiary) {
            if (str_starts_with((string) $beneficiary->beneficiary_ref, 'PENDING-')) {
                $beneficiary->beneficiary_ref = self::buildReference($beneficiary->getKey());
                $beneficiary->saveQuietly();
            }
        });

        // Beneficiary stores every geographic level side by side so reporting
        // can filter by state/LGA/ward/community without joins. Foreign keys
        // prove each level exists; they cannot prove the levels describe the
        // same branch of the hierarchy, so contradictory combinations are
        // rejected here (Phase 2.1).
        static::saving(function (Beneficiary $beneficiary) {
            GeographicHierarchy::assertAddressIsWithinHierarchy(
                $beneficiary->country_id !== null ? (int) $beneficiary->country_id : null,
                $beneficiary->state_id !== null ? (int) $beneficiary->state_id : null,
                $beneficiary->local_government_area_id !== null ? (int) $beneficiary->local_government_area_id : null,
                $beneficiary->ward_id !== null ? (int) $beneficiary->ward_id : null,
                $beneficiary->community_id !== null ? (int) $beneficiary->community_id : null,
            );

            // person_id is this child's own row in the household roster. If both
            // it and the beneficiary name a household, they must be the same
            // one. A roster person with no household yet is incomplete data,
            // not a contradiction, so it is left alone.
            if ($beneficiary->person_id !== null && $beneficiary->household_id !== null) {
                $rosterHouseholdId = Person::query()
                    ->whereKey($beneficiary->person_id)
                    ->value('household_id');

                if ($rosterHouseholdId !== null && (int) $rosterHouseholdId !== (int) $beneficiary->household_id) {
                    throw ValidationException::withMessages([
                        'person_id' => 'The household roster record for this child belongs to a different household.',
                    ]);
                }
            }
        });
    }

    /**
     * Build the stable internal reference for a beneficiary primary key,
     * e.g. WICHOV-000001. The prefix and padding are configurable in
     * config/orwems.php so no programme identity is hard-coded.
     */
    public static function buildReference(int|string $key): string
    {
        $prefix = (string) config('orwems.beneficiary_ref_prefix', 'WICHOV');
        $padding = (int) config('orwems.beneficiary_ref_padding', 6);

        return sprintf('%s-%s', $prefix, str_pad((string) $key, $padding, '0', STR_PAD_LEFT));
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function localGovernmentArea(): BelongsTo
    {
        return $this->belongsTo(LocalGovernmentArea::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * The most specific place this record carries, for scope decisions.
     *
     * A beneficiary stores every geographic level side by side (so reporting
     * can filter by state without joins), but scope needs ONE anchor to walk
     * ancestors from — the deepest level present. `allowsScope()` then accepts
     * a scope at that level or any level above it, which is how a state scope
     * covers a community-level record without any copying.
     *
     * The first loaded relation wins over a fresh query: the policy always
     * calls this on an already-loaded record, and re-querying each level here
     * would add five queries to every authorization check.
     */
    public function geography(): ?Model
    {
        return $this->community
            ?? $this->ward
            ?? $this->localGovernmentArea
            ?? $this->state
            ?? $this->country;
    }

    /**
     * Beneficiaries this operator may reach, as a query.
     *
     * The ONLY query the index page reads from. It delegates to
     * `ScopeAuthorization::applyBeneficiaryScope()` so the listing and the
     * detail page share one definition of "in scope" — they cannot disagree.
     * Filtering happens in SQL, in the database, before any row is loaded:
     * an out-of-scope record never reaches PHP, let alone Blade.
     *
     * Soft-deleted (archived) records are excluded here, not in the
     * controller, so no future listing path can forget the exclusion.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Beneficiary>
     */
    public function scopeAccessibleTo(\Illuminate\Database\Eloquent\Builder $query, ?User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return ScopeAuthorization::applyBeneficiaryScope($user, $query);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->orderBy('assessment_date');
    }

    public function needs(): HasMany
    {
        return $this->hasMany(Need::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    /**
     * Document metadata attached to this child. Files are held on private
     * storage by a later phase; no upload or download path exists yet.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
